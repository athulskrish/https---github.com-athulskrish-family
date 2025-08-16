<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/SecurityMiddleware.php';
require_once 'includes/PhotoHandler.php';

// Check if user is logged in
redirect_if_not_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'])) {
    $error_msg = "Invalid request.";
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => $error_msg]);
        exit;
    }
    $_SESSION['flash_message'] = $error_msg;
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

$tree_id = (int)$_POST['tree_id'];
$action = $_POST['action'] ?? 'add_member';

// Verify user has access to this tree
$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN owner_id = ? THEN 'owner'
            WHEN EXISTS (
                SELECT 1 FROM tree_sharing 
                WHERE tree_id = ? AND user_id = ? 
                AND permission_level IN ('edit', 'admin')
            ) THEN 'editor'
            ELSE NULL
        END as access_level
    FROM family_trees 
    WHERE id = ?
");
$stmt->execute([$_SESSION['user_id'], $tree_id, $_SESSION['user_id'], $tree_id]);
$access = $stmt->fetchColumn();

if (!$access) {
    $error_msg = "You don't have permission to add members to this tree.";
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => $error_msg]);
        exit;
    }
    $_SESSION['flash_message'] = $error_msg;
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

// Helper function to check if column exists
function columnExists($conn, $table, $column) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error checking column existence: " . $e->getMessage());
        return false;
    }
}

// Handle different actions
if ($action === 'add_member') {
    // Validate required fields - support both naming conventions
    $first_name = SecurityMiddleware::sanitizeInput($_POST['first_name'] ?? $_POST['name'] ?? '');
    $last_name = SecurityMiddleware::sanitizeInput($_POST['last_name'] ?? '');
    $middle_name = SecurityMiddleware::sanitizeInput($_POST['middle_name'] ?? '');
    $maiden_name = SecurityMiddleware::sanitizeInput($_POST['maiden_name'] ?? '');
    $gender = SecurityMiddleware::sanitizeInput($_POST['gender'] ?? '');
    $birth_date = SecurityMiddleware::sanitizeInput($_POST['date_of_birth'] ?? $_POST['birth_date'] ?? '');
    $death_date = SecurityMiddleware::sanitizeInput($_POST['date_of_death'] ?? '');
    $birth_place = SecurityMiddleware::sanitizeInput($_POST['birth_place'] ?? '');
    $death_place = SecurityMiddleware::sanitizeInput($_POST['death_place'] ?? '');
    $notes = SecurityMiddleware::sanitizeInput($_POST['notes'] ?? '');

    // Process dates - convert empty strings to NULL for proper database storage
    $birth_date = !empty($birth_date) ? $birth_date : null;
    $death_date = !empty($death_date) ? $death_date : null;

    // For backward compatibility, if name is provided but not first_name/last_name
    if (!$first_name && !$last_name && !empty($_POST['name'])) {
        $name_parts = explode(' ', trim($_POST['name']), 2);
        $first_name = $name_parts[0];
        $last_name = $name_parts[1] ?? '';
    }

    $required_fields = ['first_name', 'gender'];
    foreach ($required_fields as $field) {
        if (empty($$field)) {
            $error_msg = "Missing required field: $field.";
            if ($is_ajax) {
                echo json_encode(['success' => false, 'error' => $error_msg]);
                exit;
            }
            $_SESSION['flash_message'] = $error_msg;
            $_SESSION['flash_type'] = "danger";
            redirect_to('dashboard.php');
        }
    }

    // Validate date logic - death date cannot be before birth date
    if ($birth_date && $death_date && $death_date < $birth_date) {
        $error_msg = "Date of death cannot be before date of birth.";
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => $error_msg]);
            exit;
        }
        $_SESSION['flash_message'] = $error_msg;
        $_SESSION['flash_type'] = "danger";
        redirect_to('dashboard.php');
    }

    // Log database connection status
    if ($debug_mode) {
        if ($conn) {
            error_log("Database connection established successfully.");
        } else {
            error_log("Database connection failed.");
        }
    }

    try {
        $conn->beginTransaction();

        // Handle photo upload using enhanced PhotoHandler
        $photo_url = null;
        $photo_filename = null;
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            // Use PhotoHandler for more robust upload handling
            $upload_result = PhotoHandler::uploadPhoto($_FILES['photo'], 0); // Will update with actual member ID
            
            if ($upload_result['success']) {
                $photo_filename = $upload_result['filename'];
                $photo_url = 'assets/uploads/photos/' . $photo_filename;
            } else {
                throw new Exception("Photo upload failed: " . $upload_result['error']);
            }
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors with detailed messages
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            $error_msg = $upload_errors[$_FILES['photo']['error']] ?? 'Unknown upload error';
            throw new Exception("Upload error: " . $error_msg);
        }

        // Check if columns exist in the people table
        $hasMaildenName = columnExists($conn, 'people', 'maiden_name');
        $hasDateOfDeath = columnExists($conn, 'people', 'date_of_death');
        $hasDeathPlace = columnExists($conn, 'people', 'death_place');
        
        if ($debug_mode) {
            error_log("Maiden name column exists: " . ($hasMaildenName ? 'Yes' : 'No'));
            error_log("Date of death column exists: " . ($hasDateOfDeath ? 'Yes' : 'No'));
            error_log("Death place column exists: " . ($hasDeathPlace ? 'Yes' : 'No'));
        }

        // Build dynamic SQL query based on column availability and data presence
        $columns = ['tree_id', 'first_name', 'middle_name', 'last_name'];
        $placeholders = ['?', '?', '?', '?'];
        $params = [$tree_id, $first_name, $middle_name, $last_name];

        // Add maiden_name if column exists and has value
        if ($hasMaildenName) {
            $columns[] = 'maiden_name';
            $placeholders[] = '?';
            $params[] = $maiden_name;
        } elseif ($maiden_name && $debug_mode) {
            error_log("Maiden name provided but column doesn't exist - omitting: " . $maiden_name);
        }

        // Add standard columns (always present)
        $columns = array_merge($columns, ['gender', 'date_of_birth', 'birth_place', 'photo_url', 'notes']);
        $placeholders = array_merge($placeholders, ['?', '?', '?', '?', '?']);
        $params = array_merge($params, [$gender, $birth_date, $birth_place, $photo_url, $notes]);

        // Add date_of_death only if column exists (NULL values are handled properly by MySQL)
        if ($hasDateOfDeath) {
            $columns[] = 'date_of_death';
            $placeholders[] = '?';
            $params[] = $death_date; // Will be NULL for living people
            
            if ($debug_mode) {
                $status = $death_date ? "deceased (date: $death_date)" : "living (NULL death date)";
                error_log("Adding death date field - Person status: $status");
            }
        } elseif ($death_date && $debug_mode) {
            error_log("Death date provided but column doesn't exist - omitting: " . $death_date);
        }

        // Add death_place only if column exists
        if ($hasDeathPlace) {
            $columns[] = 'death_place';
            $placeholders[] = '?';
            $params[] = $death_place;
        } elseif ($death_place && $debug_mode) {
            error_log("Death place provided but column doesn't exist - omitting: " . $death_place);
        }

        // Prepare and execute the SQL statement
        $sql = "INSERT INTO people (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $conn->prepare($sql);

        // Log SQL query execution
        if ($debug_mode) {
        }

        $stmt->execute($params);
        $person_id = $conn->lastInsertId();

        // Update photo filename with actual member ID for better organization
        if ($photo_filename) {
            $new_filename = $person_id . '_' . time() . '.' . pathinfo($photo_filename, PATHINFO_EXTENSION);
            $old_path = 'assets/uploads/photos/' . $photo_filename;
            $new_path = 'assets/uploads/photos/' . $new_filename;
            
            if (file_exists($old_path)) {
                rename($old_path, $new_path);
                
                // Update database with new filename
                $update_stmt = $conn->prepare("UPDATE people SET photo_url = ? WHERE id = ?");
                $update_stmt->execute([$new_path, $person_id]);
                $photo_url = $new_path;
            }
        }

        // Handle relationships with enhanced validation
        if (isset($_POST['relationship_type']) && is_array($_POST['relationship_type'])) {
            $relationship_stmt = $conn->prepare("
                INSERT INTO relationships (
                    person1_id, person2_id, relationship_type
                ) VALUES (?, ?, ?)
            ");

            foreach ($_POST['relationship_type'] as $index => $type) {
                if (empty($type) || empty($_POST['related_person'][$index])) {
                    continue;
                }

                $related_person_id = (int)$_POST['related_person'][$index];
                
                // Verify related person belongs to the same tree
                $verify_stmt = $conn->prepare("
                    SELECT id FROM people WHERE id = ? AND tree_id = ?
                ");
                $verify_stmt->execute([$related_person_id, $tree_id]);
                if (!$verify_stmt->fetch()) {
                    throw new Exception("Invalid relationship selection.");
                }

                // For parent-child relationships, ensure correct direction
                if ($type === 'parent-child') {
                    $relationship_stmt->execute([$related_person_id, $person_id, $type]);
                } else {
                    $relationship_stmt->execute([$person_id, $related_person_id, $type]);
                }
            }
        }

        $conn->commit();

        $success_msg = "Family member added successfully!";
        
        if ($is_ajax) {
            echo json_encode([
                'success' => true, 
                'member_id' => $person_id,
                'photo_url' => $photo_url,
                'message' => $success_msg,
                'maiden_name_supported' => $hasMaildenName,
                'date_of_death_supported' => $hasDateOfDeath,
                'death_place_supported' => $hasDeathPlace
            ]);
            exit;
        }

        $_SESSION['flash_message'] = $success_msg;
        $_SESSION['flash_type'] = "success";
        redirect_to('family-tree.php?id=' . $tree_id);

    } catch (Exception $e) {
        $conn->rollBack();
        
        // Clean up uploaded files on error
        if ($photo_url && file_exists($photo_url)) {
            unlink($photo_url);
        }
        if ($photo_filename) {
            $temp_path = 'assets/uploads/photos/' . $photo_filename;
            if (file_exists($temp_path)) {
                unlink($temp_path);
            }
        }

        // Enhanced error logging
        if ($debug_mode) {
            error_log("Exception caught: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
        }

        $error_msg = "Error adding family member: " . $e->getMessage();
        
        if ($is_ajax) {
            echo json_encode(['success' => false, 'error' => $error_msg]);
            exit;
        }

        $_SESSION['flash_message'] = $error_msg;
        $_SESSION['flash_type'] = "danger";
        redirect_to('family-tree.php?id=' . $tree_id);
    }
} else {
    // Handle other actions or invalid action
    $error_msg = "Invalid action specified.";
    
    if ($is_ajax) {
        echo json_encode(['success' => false, 'error' => $error_msg]);
        exit;
    }
    
    $_SESSION['flash_message'] = $error_msg;
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}
?>