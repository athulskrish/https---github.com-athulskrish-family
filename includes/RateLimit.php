	<?php
	require_once 'db.php';

	class RateLimit {
		private $pdo;
		private $limits = [
			// Login and registration endpoints
			'auth' => ['requests' => 5, 'window' => 300], // 5 requests per 5 minutes
			
			// Media upload endpoints
			'upload' => ['requests' => 10, 'window' => 600], // 10 uploads per 10 minutes
			
			// Tree modification endpoints
			'tree' => ['requests' => 60, 'window' => 60], // 60 requests per minute
			
			// Export/Import functionality
			'export' => ['requests' => 2, 'window' => 300], // 2 exports per 5 minutes
			'import' => ['requests' => 2, 'window' => 300], // 2 imports per 5 minutes
			
			// Search functionality
			'search' => ['requests' => 30, 'window' => 60], // 30 searches per minute
			
			// Default limit for other endpoints
			'default' => ['requests' => 120, 'window' => 60] // 120 requests per minute
		];

		public function __construct() {
			try {
				$this->pdo = Database::getInstance()->getConnection();
				if (!$this->pdo) {
					throw new Exception("Database connection not available");
				}
				$this->createRateLimitTable();
			} catch (Exception $e) {
				error_log("RateLimit initialization error: " . $e->getMessage());
				// Set a flag that we're in fallback mode - won't block any requests
				$this->pdo = null;
			}
		}

		private function createRateLimitTable() {
			try {
				// Check if table exists first
				$tableExists = false;
				try {
					$check = $this->pdo->query("SELECT 1 FROM rate_limits LIMIT 1");
					$tableExists = true;
				} catch (PDOException $e) {
					// Table doesn't exist
					$tableExists = false;
				}

				if (!$tableExists) {
					// Create the table if it doesn't exist
					$sql = "CREATE TABLE IF NOT EXISTS rate_limits (
						id INT AUTO_INCREMENT PRIMARY KEY,
						ip_address VARCHAR(45) NOT NULL,
						endpoint VARCHAR(50) NOT NULL DEFAULT 'default',
						requests INT DEFAULT 1,
						window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
						INDEX idx_ip_endpoint (ip_address, endpoint)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
					$this->pdo->exec($sql);
				} else {
					// Table exists, check for endpoint column
					$result = $this->pdo->query("SHOW COLUMNS FROM rate_limits LIKE 'endpoint'");
					if ($result->rowCount() == 0) {
						// Add endpoint column if it doesn't exist
						$this->pdo->exec("ALTER TABLE rate_limits 
							ADD COLUMN endpoint VARCHAR(50) NOT NULL DEFAULT 'default' AFTER ip_address,
							ADD INDEX idx_ip_endpoint (ip_address, endpoint)");
					}
				}
				
			} catch (PDOException $e) {
				// Log the specific error for debugging
				error_log("Database Error in RateLimit->createRateLimitTable(): " . $e->getMessage());
				error_log("SQL State: " . $e->getCode());
				
				// Try to create a minimal version of the table if all else fails
				try {
					$sql = "CREATE TABLE IF NOT EXISTS rate_limits (
						id INT AUTO_INCREMENT PRIMARY KEY,
						ip_address VARCHAR(45) NOT NULL,
						endpoint VARCHAR(50) NOT NULL DEFAULT 'default',
						requests INT DEFAULT 1,
						window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
					)";
					$this->pdo->exec($sql);
				} catch (PDOException $e2) {
					error_log("Failed fallback attempt in RateLimit->createRateLimitTable(): " . $e2->getMessage());
					throw new Exception("Failed to initialize rate limiting. Please contact support if this persists.");
				}
			}
		}

		public function check($endpoint = 'default') {
			// If database connection isn't available, allow all requests
			if (!$this->pdo) {
				error_log("Rate limiting disabled - database connection unavailable");
				return true;
			}

			try {
				$ip = $this->getClientIP();
				$limit = $this->limits[$endpoint] ?? $this->limits['default'];
				
				// Clean up old records
				$this->cleanup($endpoint);
				
				// Check current request count
				$sql = "SELECT COUNT(*) as count FROM rate_limits 
						WHERE ip_address = :ip 
						AND endpoint = :endpoint 
						AND window_start > DATE_SUB(NOW(), INTERVAL :window SECOND)";
				
				$stmt = $this->pdo->prepare($sql);
				$stmt->execute([
					':ip' => $ip,
					':endpoint' => $endpoint,
					':window' => $limit['window']
				]);
				$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

			if ($count >= $limit['requests']) {
				$this->logExcess($ip, $endpoint);
				return false;
			}

			// Log this request
			$this->logRequest($ip, $endpoint);
			return true;
		} catch (PDOException $e) {
			error_log("Error checking rate limit: " . $e->getMessage());
			return true; // Allow request on error
		}
	}

		private function cleanup($endpoint) {
			$limit = $this->limits[$endpoint] ?? $this->limits['default'];
			$sql = "DELETE FROM rate_limits 
					WHERE endpoint = :endpoint 
					AND window_start < DATE_SUB(NOW(), INTERVAL :window SECOND)";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				':endpoint' => $endpoint,
				':window' => $limit['window']
			]);
		}

		private function logRequest($ip, $endpoint) {
			$sql = "INSERT INTO rate_limits (ip_address, endpoint) VALUES (:ip, :endpoint)";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				':ip' => $ip,
				':endpoint' => $endpoint
			]);
		}

		private function logExcess($ip, $endpoint) {
			// Log excessive requests for monitoring
			error_log("Rate limit exceeded for IP: {$ip} on endpoint: {$endpoint}");
		}

		private function getClientIP() {
			// Check for proxies
			if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				return $_SERVER['HTTP_X_FORWARDED_FOR'];
			}
			return $_SERVER['REMOTE_ADDR'];
		}

		public function getRemainingRequests($endpoint = 'default') {
			$ip = $this->getClientIP();
			$limit = $this->limits[$endpoint] ?? $this->limits['default'];
			
			$sql = "SELECT COUNT(*) as count FROM rate_limits 
					WHERE ip_address = :ip 
					AND endpoint = :endpoint 
					AND window_start > DATE_SUB(NOW(), INTERVAL :window SECOND)";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				':ip' => $ip,
				':endpoint' => $endpoint,
				':window' => $limit['window']
			]);
			$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
			
			return max(0, $limit['requests'] - $count);
		}

		public function getTimeUntilReset($endpoint = 'default') {
			$ip = $this->getClientIP();
			$limit = $this->limits[$endpoint] ?? $this->limits['default'];
			
			$sql = "SELECT MIN(window_start) as oldest_request FROM rate_limits 
					WHERE ip_address = :ip 
					AND endpoint = :endpoint 
					AND window_start > DATE_SUB(NOW(), INTERVAL :window SECOND)";
			
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute([
				':ip' => $ip,
				':endpoint' => $endpoint,
				':window' => $limit['window']
			]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$row['oldest_request']) {
				return 0;
			}
			
			$oldestTime = strtotime($row['oldest_request']);
			return max(0, ($oldestTime + $limit['window']) - time());
		}
	}

	$rateLimit = new RateLimit();
