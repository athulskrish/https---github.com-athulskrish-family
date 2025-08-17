	<?php
	// Include core files
	require_once 'includes/config.php';
	require_once 'includes/db.php';
	// require_once 'includes/functions.php';
	require_once 'includes/auth.php';
	require_once 'includes/security.php';
	require_once 'includes/FamilyRelationships.php';

	// Check if user is logged in
	require_login();

	// Enhanced FamilyRelationships class with category mapping
	if (!class_exists('FamilyRelationships')) {
	class FamilyRelationships {
		public static $relationships = [
			// Direct Line (Vertical)
			'parent' => [
				'father' => 'Father',
				'mother' => 'Mother',
				'step-father' => 'Step-Father',
				'step-mother' => 'Step-Mother',
				'adoptive-father' => 'Adoptive Father',
				'adoptive-mother' => 'Adoptive Mother'
			],
			'child' => [
				'son' => 'Son',
				'daughter' => 'Daughter',
				'step-son' => 'Step-Son',
				'step-daughter' => 'Step-Daughter',
				'adoptive-son' => 'Adoptive Son',
				'adoptive-daughter' => 'Adoptive Daughter'
			],
			'grandparent' => [
				'grandfather' => 'Grandfather',
				'grandmother' => 'Grandmother',
				'step-grandfather' => 'Step-Grandfather',
				'step-grandmother' => 'Step-Grandmother'
			],
			'grandchild' => [
				'grandson' => 'Grandson',
				'granddaughter' => 'Granddaughter'
			],
			'great-grandparent' => [
				'great-grandfather' => 'Great-Grandfather',
				'great-grandmother' => 'Great-Grandmother'
			],
			'great-grandchild' => [
				'great-grandson' => 'Great-Grandson',
				'great-granddaughter' => 'Great-Granddaughter'
			],
			
			// Siblings (Same Generation)
			'sibling' => [
				'brother' => 'Brother',
				'sister' => 'Sister',
				'half-brother' => 'Half-Brother',
				'half-sister' => 'Half-Sister',
				'step-brother' => 'Step-Brother',
				'step-sister' => 'Step-Sister'
			],
			
			// Aunts and Uncles
			'aunt-uncle' => [
				'aunt' => 'Aunt',
				'uncle' => 'Uncle',
				'great-aunt' => 'Great-Aunt',
				'great-uncle' => 'Great-Uncle'
			],
			
			// Cousins
			'cousin' => [
				'first-cousin' => 'First Cousin',
				'second-cousin' => 'Second Cousin',
				'third-cousin' => 'Third Cousin',
				'first-cousin-once-removed' => 'First Cousin Once Removed',
				'first-cousin-twice-removed' => 'First Cousin Twice Removed'
			],
			
			// In-Laws
			'spouse' => [
				'husband' => 'Husband',
				'wife' => 'Wife',
				'ex-husband' => 'Ex-Husband',
				'ex-wife' => 'Ex-Wife'
			],
			'in-law' => [
				'father-in-law' => 'Father-in-Law',
				'mother-in-law' => 'Mother-in-Law',
				'son-in-law' => 'Son-in-Law',
				'daughter-in-law' => 'Daughter-in-Law',
				'brother-in-law' => 'Brother-in-Law',
				'sister-in-law' => 'Sister-in-Law'
			],
			
			// Nieces and Nephews
			'niece-nephew' => [
				'niece' => 'Niece',
				'nephew' => 'Nephew',
				'grand-niece' => 'Grand-Niece',
				'grand-nephew' => 'Grand-Nephew'
			]
		];
		
		public static function getAllRelationships() {
			$all = [];
			foreach(self::$relationships as $category => $relations) {
				$all = array_merge($all, $relations);
			}
			return $all;
		}
		
		// Get the category for a specific relationship subtype
		public static function getRelationshipCategory($subtype) {
			foreach(self::$relationships as $category => $relations) {
				if(array_key_exists($subtype, $relations)) {
					return $category;
				}
			}
			return 'other'; // fallback
		}
		
		public static function getReciprocalRelationship($relationship) {
			$reciprocals = [
				'father' => 'son', 'mother' => 'daughter',
				'son' => 'father', 'daughter' => 'mother',
				'grandfather' => 'grandson', 'grandmother' => 'granddaughter',
				'grandson' => 'grandfather', 'granddaughter' => 'grandmother',
				'brother' => 'brother', 'sister' => 'sister',
				'husband' => 'wife', 'wife' => 'husband',
				'uncle' => 'nephew', 'aunt' => 'niece',
				'nephew' => 'uncle', 'niece' => 'aunt',
				'first-cousin' => 'first-cousin',
				'step-father' => 'step-son', 'step-mother' => 'step-daughter',
				'step-son' => 'step-father', 'step-daughter' => 'step-mother',
				'half-brother' => 'half-brother', 'half-sister' => 'half-sister',
				'step-brother' => 'step-brother', 'step-sister' => 'step-sister'
			];
			
			return $reciprocals[$relationship] ?? null;
		}
		
		// Gender-aware reciprocal relationship function
		public static function getReciprocalRelationshipSmart($relationship, $person1_id, $person2_id, $conn) {
			// Get gender of person1 to determine correct reciprocal
			$stmt = $conn->prepare("SELECT gender FROM people WHERE id = ?");
			$stmt->execute([$person1_id]);
			$person1_gender = $stmt->fetchColumn();
			
			// Special handling for parent-child relationships
			if ($relationship === 'son' || $relationship === 'daughter') {
				return strtoupper($person1_gender) === 'M' ? 'father' : 'mother';
			}
			
			if ($relationship === 'father' || $relationship === 'mother') {
				$stmt = $conn->prepare("SELECT gender FROM people WHERE id = ?");
				$stmt->execute([$person2_id]);
				$person2_gender = $stmt->fetchColumn();
				return strtoupper($person2_gender) === 'M' ? 'son' : 'daughter';
			}
			
			// For other relationships, use standard reciprocal
			return self::getReciprocalRelationship($relationship);
		}
	}
	}

	// Get database connection
	$db = Database::getInstance();
	$conn = $db->getConnection();

	// Get current tree data
	$current_tree_id = $_SESSION['current_tree_id'] ?? 1;

	// Check if user has access to this tree
	if (!can_access_trees($current_tree_id)) {
		$_SESSION['flash_message'] = "You don't have access to this family tree.";
		$_SESSION['flash_type'] = 'error';
		redirect_to('dashboard.php');
		exit();
	}

	$family_data = getFamilyTreeData($current_tree_id, $conn);

	// Add sample data if no family members exist (for demo purposes)
	if (empty($family_data['members'])) {
		$family_data = createSampleFamilyData();
	}

	// Get all family members and relationships for a tree (local implementation for demo)
	function getFamilyTreeData($tree_id, $conn) {
		$members = [];
		$relationships = [];
		
		// Get all members
		$query = "SELECT * FROM people WHERE tree_id = ? ORDER BY date_of_birth ASC";
		$stmt = $conn->prepare($query);
		$stmt->execute([$tree_id]);
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		foreach($result as $member) {
			$members[$member['id']] = $member;
		}
		
		// Get all relationships with both directions
		$query = "SELECT r.*, 
			p1.first_name as person1_first_name, p1.last_name as person1_last_name,
			p2.first_name as person2_first_name, p2.last_name as person2_last_name
			FROM relationships r
			JOIN people p1 ON r.person1_id = p1.id
			JOIN people p2 ON r.person2_id = p2.id
			WHERE p1.tree_id = ?
			ORDER BY r.person1_id, r.person2_id";
		
		$stmt = $conn->prepare($query);
		$stmt->execute([$tree_id]);
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		foreach($result as $rel) {
			$rel['person1_name'] = trim($rel['person1_first_name'] . ' ' . $rel['person1_last_name']);
			$rel['person2_name'] = trim($rel['person2_first_name'] . ' ' . $rel['person2_last_name']);
			
			$relationships[] = [
				'person1_id' => $rel['person1_id'],
				'person2_id' => $rel['person2_id'], 
				'relationship_type' => $rel['relationship_type'],
				'relationship_subtype' => $rel['relationship_subtype'],
				'marriage_date' => $rel['marriage_date'],
				'marriage_place' => $rel['marriage_place'],
				'person1_name' => $rel['person1_name'],
				'person2_name' => $rel['person2_name']
			];
		}
		
		return ['members' => $members, 'relationships' => $relationships];
	}

	// Create sample family data for demonstration
	function createSampleFamilyData() {
		return [
			'members' => [
				1 => [
					'id' => 1,
					'first_name' => 'John',
					'last_name' => 'Smith',
					'gender' => 'M',
					'date_of_birth' => '1950-03-15',
					'photo_url' => '',
					'is_living' => true
				],
				2 => [
					'id' => 2,
					'first_name' => 'Mary',
					'last_name' => 'Smith',
					'maiden_name' => 'Johnson',
					'gender' => 'F',
					'date_of_birth' => '1952-07-22',
					'photo_url' => '',
					'is_living' => true
				],
				3 => [
					'id' => 3,
					'first_name' => 'Michael',
					'last_name' => 'Smith',
					'gender' => 'M',
					'date_of_birth' => '1975-01-10',
					'photo_url' => '',
					'is_living' => true
				],
				4 => [
					'id' => 4,
					'first_name' => 'Sarah',
					'last_name' => 'Smith',
					'maiden_name' => 'Davis',
					'gender' => 'F',
					'date_of_birth' => '1977-11-05',
					'photo_url' => '',
					'is_living' => true
				],
				5 => [
					'id' => 5,
					'first_name' => 'Jennifer',
					'last_name' => 'Smith',
					'gender' => 'F',
					'date_of_birth' => '1980-09-18',
					'photo_url' => '',
					'is_living' => true
				],
				6 => [
					'id' => 6,
					'first_name' => 'Emma',
					'last_name' => 'Smith',
					'gender' => 'F',
					'date_of_birth' => '2005-04-12',
					'photo_url' => '',
					'is_living' => true
				],
				7 => [
					'id' => 7,
					'first_name' => 'James',
					'last_name' => 'Smith',
					'gender' => 'M',
					'date_of_birth' => '2008-08-20',
					'photo_url' => '',
					'is_living' => true
				],
				8 => [
					'id' => 8,
					'first_name' => 'Lisa',
					'last_name' => 'Smith',
					'gender' => 'F',
					'date_of_birth' => '2010-12-03',
					'photo_url' => '',
					'is_living' => true
				]
			],
			'relationships' => [
				// John and Mary are married
				['person1_id' => 1, 'person2_id' => 2, 'relationship_subtype' => 'husband'],
				['person1_id' => 2, 'person2_id' => 1, 'relationship_subtype' => 'wife'],
				
				// Michael and Sarah are married
				['person1_id' => 3, 'person2_id' => 4, 'relationship_subtype' => 'husband'],
				['person1_id' => 4, 'person2_id' => 3, 'relationship_subtype' => 'wife'],
				
				// John is father to Michael and Jennifer
				['person1_id' => 1, 'person2_id' => 3, 'relationship_subtype' => 'father'],
				['person1_id' => 3, 'person2_id' => 1, 'relationship_subtype' => 'son'],
				['person1_id' => 1, 'person2_id' => 5, 'relationship_subtype' => 'father'],
				['person1_id' => 5, 'person2_id' => 1, 'relationship_subtype' => 'daughter'],
				
				// Mary is mother to Michael and Jennifer
				['person1_id' => 2, 'person2_id' => 3, 'relationship_subtype' => 'mother'],
				['person1_id' => 3, 'person2_id' => 2, 'relationship_subtype' => 'son'],
				['person1_id' => 2, 'person2_id' => 5, 'relationship_subtype' => 'mother'],
				['person1_id' => 5, 'person2_id' => 2, 'relationship_subtype' => 'daughter'],
				
				// Michael is father to Emma, James, and Lisa
				['person1_id' => 3, 'person2_id' => 6, 'relationship_subtype' => 'father'],
				['person1_id' => 6, 'person2_id' => 3, 'relationship_subtype' => 'daughter'],
				['person1_id' => 3, 'person2_id' => 7, 'relationship_subtype' => 'father'],
				['person1_id' => 7, 'person2_id' => 3, 'relationship_subtype' => 'son'],
				['person1_id' => 3, 'person2_id' => 8, 'relationship_subtype' => 'father'],
				['person1_id' => 8, 'person2_id' => 3, 'relationship_subtype' => 'daughter'],
				
				// Sarah is mother to Emma, James, and Lisa
				['person1_id' => 4, 'person2_id' => 6, 'relationship_subtype' => 'mother'],
				['person1_id' => 6, 'person2_id' => 4, 'relationship_subtype' => 'daughter'],
				['person1_id' => 4, 'person2_id' => 7, 'relationship_subtype' => 'mother'],
				['person1_id' => 7, 'person2_id' => 4, 'relationship_subtype' => 'son'],
				['person1_id' => 4, 'person2_id' => 8, 'relationship_subtype' => 'mother'],
				['person1_id' => 8, 'person2_id' => 4, 'relationship_subtype' => 'daughter'],
				
				// Sibling relationships
				['person1_id' => 3, 'person2_id' => 5, 'relationship_subtype' => 'brother'],
				['person1_id' => 5, 'person2_id' => 3, 'relationship_subtype' => 'sister'],
				['person1_id' => 6, 'person2_id' => 7, 'relationship_subtype' => 'sister'],
				['person1_id' => 7, 'person2_id' => 6, 'relationship_subtype' => 'brother'],
				['person1_id' => 6, 'person2_id' => 8, 'relationship_subtype' => 'sister'],
				['person1_id' => 8, 'person2_id' => 6, 'relationship_subtype' => 'sister'],
				['person1_id' => 7, 'person2_id' => 8, 'relationship_subtype' => 'brother'],
				['person1_id' => 8, 'person2_id' => 7, 'relationship_subtype' => 'sister']
			]
		];
	}


	// Tree access control function
	function can_access_trees($tree_id) {
		if (!is_logged_in()) {
			return false;
		}
		
		$db = Database::getInstance();
		$conn = $db->getConnection();
		
		// Check if user owns the tree or has been granted access
		$stmt = $conn->prepare("
			SELECT 1 FROM tree_access 
			WHERE tree_id = ? AND user_id = ?
			UNION
			SELECT 1 FROM family_trees 
			WHERE id = ? AND owner_id = ?
			LIMIT 1
		");
		
		$user_id = $_SESSION['user_id'];
		$stmt->execute([$tree_id, $user_id, $tree_id, $user_id]);
		
		return $stmt->rowCount() > 0;
	}

	// Build comprehensive relationships map for the hierarchical tree
	function buildRelationshipsMaps($family_data) {
		$map = [];
		
		if (!isset($family_data['relationships']) || !is_array($family_data['relationships'])) {
			return $map;
		}
		
		foreach ($family_data['relationships'] as $rel) {
			if (!isset($rel['person1_id'], $rel['person2_id'], $rel['relationship_subtype'])) {
				continue;
			}
			
			$person1 = $rel['person1_id'];
			$person2 = $rel['person2_id'];
			$relationship = $rel['relationship_subtype'];
			
			if (!isset($map[$person1])) {
				$map[$person1] = [];
			}
			
			$map[$person1][] = [
				'type' => $relationship,
				'member_id' => $person2,
				'marriage_date' => $rel['marriage_date'] ?? null,
				'marriage_place' => $rel['marriage_place'] ?? null
			];
		}
		
		return $map;
	}

	// Find all spouses of a person
	function findSpouse($person_id, $all_members, $relationships_map) {
		$spouses = [];
		
		if (!isset($relationships_map[$person_id])) {
			return $spouses;
		}
		
		foreach ($relationships_map[$person_id] as $rel) {
			if (in_array($rel['type'], ['husband', 'wife', 'spouse', 'partner'])) {
				$spouse = findMemberByIds($all_members, $rel['member_id']);
				if ($spouse) {
					$spouses[] = $spouse;
				}
			}
		}
		
		return $spouses;
	}

	// Find children of a specific parent
	function findChildrens($parent_id, $all_members, $relationships_map) {
		$children = [];
		
		if (!isset($relationships_map[$parent_id])) {
			return $children;
		}
		
		foreach ($relationships_map[$parent_id] as $rel) {
			// Parent -> Child relations are marked as father/mother/parent
			if (in_array($rel['type'], ['father', 'mother', 'parent'])) {
				$child = findMemberByIds($all_members, $rel['member_id']);
				if ($child) {
					$children[] = $child;
				}
			}
		}
		
		return $children;
	}

	// Helper function to find a member by ID
	function findMemberByIds($members, $id) {
		foreach ($members as $member) {
			if ($member['id'] == $id) {
				return $member;
			}
		}
		return null;
	}

	// Enhanced hierarchical family tree rendering function
	function renderHierarchicalFamilyTree($family_data) {
		if (empty($family_data['members'])) {
			return '<div class="alert alert-info">No family members found. Add your first family member!</div>';
		}

		// Build relationship graph
		$relationships_map = buildRelationshipsMaps($family_data);
		
		// Find the root generation (ancestors without parents)
		$root_members = findRootMembers($family_data['members'], $relationships_map);
		
		if (empty($root_members)) {
			return '<div class="alert alert-warning">No clear family structure found. Please ensure parent-child relationships are properly defined.</div>';
		}

		// Build generational layers instead of hierarchical nesting
		$generations = buildGenerationalLayers($family_data['members'], $relationships_map);
		
		// Render the tree with flat generational structure
		$output = '<div class="hierarchical-tree-container">';
		$output .= renderGenerationalTree($generations, $relationships_map);
		$output .= '</div>';
		
		return $output;
	}

	// Build flat generational layers
	function buildGenerationalLayers($all_members, $relationships_map) {
		$generations = [];
		$member_levels = [];
		$processed = [];
		
		// Find root generation (no parents)
		$roots = findRootMembers($all_members, $relationships_map);
		
		// Set root generation level to 0
		foreach ($roots as $root) {
			$member_levels[$root['id']] = 0;
			if (!isset($generations[0])) {
				$generations[0] = [];
			}
			$generations[0][] = $root;
			$processed[$root['id']] = true;
		}
		
		// Iteratively find children and assign them to next generation
		$current_level = 0;
		$max_iterations = 10; // Safety limit
		
		while ($current_level < $max_iterations) {
			$found_children = false;
			
			if (isset($generations[$current_level])) {
				foreach ($generations[$current_level] as $parent) {
					$children = findChildrens($parent['id'], $all_members, $relationships_map);
					
					foreach ($children as $child) {
						if (!isset($processed[$child['id']])) {
							$child_level = $current_level + 1;
							$member_levels[$child['id']] = $child_level;
							
							if (!isset($generations[$child_level])) {
								$generations[$child_level] = [];
							}
							$generations[$child_level][] = $child;
							$processed[$child['id']] = true;
							$found_children = true;
						}
					}
				}
			}
			
			if (!$found_children) {
				break;
			}
			
			$current_level++;
		}
		
		return $generations;
	}

	// Render generational tree with proper line structure
	function renderGenerationalTree($generations, $relationships_map) {
		$output = '';
		
		foreach ($generations as $level => $members) {
			$output .= '<div class="generation-row level-' . $level . '">';
			
			// Group members by family units (spouses together)
			$family_units = groupMembersIntoFamilyUnits($members, $relationships_map);
			
			foreach ($family_units as $unit) {
				$output .= '<div class="family-unit-inline">';
				
				// Render all members in this unit (spouses together)
				foreach ($unit['members'] as $index => $member) {
					$output .= renderEnhancedMemberCard($member);
					
					// Add marriage connector between spouses
					if ($index < count($unit['members']) - 1) {
						$output .= '<div class="marriage-connector-inline"></div>';
					}
				}
				
				$output .= '</div>';
			}
			
			$output .= '</div>';
			
			// Add connecting lines to next generation (parent-child connectors)
			if (isset($generations[$level + 1])) {
				$output .= '<div class="generation-connector"></div>';
			}
		}
		
		return $output;
	}

	// Group members into family units for inline display
	function groupMembersIntoFamilyUnits($members, $relationships_map) {
		$units = [];
		$processed = [];
		
		foreach ($members as $member) {
			if (in_array($member['id'], $processed)) {
				continue;
			}
			
			$unit = ['members' => [$member]];
			$processed[] = $member['id'];
			
			// Find spouse(s) in the same generation
			$spouses = findSpouse($member['id'], $members, $relationships_map);
			foreach ($spouses as $spouse) {
				if (!in_array($spouse['id'], $processed)) {
					$unit['members'][] = $spouse;
					$processed[] = $spouse['id'];
				}
			}
			
			$units[] = $unit;
		}
		
		return $units;
	}

	// Find members without parents (root generation)
	function findRootMembers($members, $relationships_map) {
		$has_parents = [];
		
		// Mark members who have parents
		foreach ($relationships_map as $person_id => $relationships) {
			foreach ($relationships as $rel) {
				if (in_array($rel['type'], ['son', 'daughter', 'child'])) {
					$has_parents[$person_id] = true;
				}
				if (in_array($rel['type'], ['father', 'mother', 'parent'])) {
					$has_parents[$rel['member_id']] = true;
				}
			}
		}
		
		$roots = [];
		foreach ($members as $member) {
			if (!isset($has_parents[$member['id']])) {
				$roots[] = $member;
			}
		}
		
		return $roots;
	}

	// Enhanced member card with better styling
	function renderEnhancedMemberCard($member) {
		$gender = strtolower($member['gender'] ?? '');
		$gender_class = $gender === 'm' ? 'male' : ($gender === 'f' ? 'female' : 'other');
		
		$is_deceased = !empty($member['date_of_death']) || (isset($member['is_living']) && !$member['is_living']);
		$deceased_class = $is_deceased ? ' deceased' : '';
		
		$output = '<div class="enhanced-member-card ' . $gender_class . $deceased_class . '" data-member-id="' . $member['id'] . '">';
		
		// Photo
		$output .= '<div class="member-photo-circle">';
		if (!empty($member['photo_url']) && file_exists($member['photo_url'])) {
			$output .= '<img src="' . htmlspecialchars($member['photo_url']) . '" alt="' . htmlspecialchars($member['first_name']) . '">';
		} else {
			$icon = $gender === 'f' ? 'fa-female' : ($gender === 'm' ? 'fa-male' : 'fa-user');
			$output .= '<div class="default-avatar"><i class="fas ' . $icon . '"></i></div>';
		}
		$output .= '</div>';
		
		// Member info
		$output .= '<div class="member-details">';
		
		// Name
		$name = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
		if ($gender === 'f' && !empty($member['maiden_name'])) {
			$name .= '<br><span class="maiden-name">(née ' . htmlspecialchars($member['maiden_name']) . ')</span>';
		}
		$output .= '<h4 class="member-name">' . $name . '</h4>';
		
		// Birth year
		if (!empty($member['date_of_birth'])) {
			$birth_year = date('Y', strtotime($member['date_of_birth']));
			$output .= '<p class="birth-year">b. ' . $birth_year . '</p>';
		}
		
		// Death year if deceased
		if ($is_deceased && !empty($member['date_of_death'])) {
			$death_year = date('Y', strtotime($member['date_of_death']));
			$output .= '<p class="death-year">d. ' . $death_year . '</p>';
		}
		
		$output .= '</div>'; // member-details
		$output .= '</div>'; // enhanced-member-card
		
		return $output;
	}

	// Include header
	require_once 'templates/header.php';
	?>

	<link rel="stylesheet" href="assets/css/hierarchical-tree.css">

	<div class="container-fluid">
		<div class="row">
			<div class="col-12">
				<h1 class="text-center my-4 text-white">
					<i class="fas fa-sitemap"></i> Hierarchical Family Tree Demo
				</h1>
				
				<!-- Statistics Panel -->
				<div class="stats-panel text-center mb-4" style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 20px; border-radius: 15px;">
					<div class="row">
						<div class="col-md-3">
							<h4><?php echo count($family_data['members']); ?></h4>
							<small>Total Members</small>
						</div>
						<div class="col-md-3">
							<h4><?php echo count(array_filter($family_data['members'], function($m) { return strtoupper($m['gender'] ?? '') == 'M'; })); ?></h4>
							<small>Males</small>
						</div>
						<div class="col-md-3">
							<h4><?php echo count(array_filter($family_data['members'], function($m) { return strtoupper($m['gender'] ?? '') == 'F'; })); ?></h4>
							<small>Females</small>
						</div>
						<div class="col-md-3">
							<h4><?php echo count($family_data['relationships']) / 2; ?></h4>
							<small>Relationships</small>
						</div>
					</div>
				</div>
				
				<!-- Tree Container -->
				<div class="tree-container mb-4">
					<?php echo renderHierarchicalFamilyTree($family_data); ?>
				</div>
				
				<!-- Tree Controls -->
				<div class="tree-controls">
					<button onclick="zoomTreeHierarchical(1.1)" title="Zoom In">
						<i class="fas fa-search-plus"></i>
					</button>
					<button onclick="zoomTreeHierarchical(0.9)" title="Zoom Out">
						<i class="fas fa-search-minus"></i>
					</button>
					<button onclick="resetZoomHierarchical()" title="Reset Zoom">
						<i class="fas fa-expand-arrows-alt"></i>
					</button>
					<button onclick="autoFitTreeHierarchical()" title="Fit to Screen">
						<i class="fas fa-compress-arrows-alt"></i>
					</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	// Hierarchical tree navigation functions
	let hierarchicalZoom = 1;

	function zoomTreeHierarchical(factor) {
		hierarchicalZoom *= factor;
		hierarchicalZoom = Math.max(0.3, Math.min(3, hierarchicalZoom));
		
		const container = document.querySelector('.hierarchical-tree-container');
		if (container) {
			container.style.transform = `scale(${hierarchicalZoom})`;
			container.style.transformOrigin = 'center top';
		}
	}

	function resetZoomHierarchical() {
		hierarchicalZoom = 1;
		const container = document.querySelector('.hierarchical-tree-container');
		if (container) {
			container.style.transform = 'scale(1)';
		}
	}

	function autoFitTreeHierarchical() {
		const container = document.querySelector('.hierarchical-tree-container');
		const parent = container.parentElement;
		
		if (container && parent) {
			const containerWidth = container.scrollWidth;
			const containerHeight = container.scrollHeight;
			const parentWidth = parent.clientWidth;
			const parentHeight = parent.clientHeight;
			
			const scaleX = (parentWidth * 0.9) / containerWidth;
			const scaleY = (parentHeight * 0.9) / containerHeight;
			
			hierarchicalZoom = Math.min(scaleX, scaleY, 1);
			container.style.transform = `scale(${hierarchicalZoom})`;
			container.style.transformOrigin = 'center top';
		}
	}

	// Initialize tree interactions
	document.addEventListener('DOMContentLoaded', function() {
		const container = document.querySelector('.hierarchical-tree-container');
		
		if (container) {
			// Mouse wheel zoom
			container.addEventListener('wheel', function(e) {
				if (e.ctrlKey || e.metaKey) {
					e.preventDefault();
					const factor = e.deltaY > 0 ? 0.9 : 1.1;
					zoomTreeHierarchical(factor);
				}
			});
			
			// Drag to pan
			let isDragging = false;
			let startX, startY, startScrollLeft, startScrollTop;
			
			container.addEventListener('mousedown', function(e) {
				isDragging = true;
				startX = e.clientX;
				startY = e.clientY;
				startScrollLeft = container.scrollLeft;
				startScrollTop = container.scrollTop;
				container.style.cursor = 'grabbing';
			});
			
			document.addEventListener('mousemove', function(e) {
				if (!isDragging) return;
				
				e.preventDefault();
				const deltaX = e.clientX - startX;
				const deltaY = e.clientY - startY;
				
				container.scrollLeft = startScrollLeft - deltaX;
				container.scrollTop = startScrollTop - deltaY;
			});
			
			document.addEventListener('mouseup', function() {
				isDragging = false;
				container.style.cursor = 'default';
			});
		}
		
		// Member card click handlers
		document.querySelectorAll('.enhanced-member-card').forEach(card => {
			card.addEventListener('click', function() {
				const memberId = this.dataset.memberId;
				if (memberId) {
					window.location.href = `member.php?id=${memberId}`;
				}
			});
		});
		
		// Auto-fit on load
		setTimeout(autoFitTreeHierarchical, 500);
	});

	// Keyboard shortcuts
	document.addEventListener('keydown', function(e) {
		if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
		
		switch(e.key) {
			case '=':
			case '+':
				if (e.ctrlKey) {
					e.preventDefault();
					zoomTreeHierarchical(1.2);
				}
				break;
			case '-':
				if (e.ctrlKey) {
					e.preventDefault();
					zoomTreeHierarchical(0.8);
				}
				break;
			case '0':
				if (e.ctrlKey) {
					e.preventDefault();
					resetZoomHierarchical();
				}
				break;
			case 'f':
				if (e.ctrlKey) {
					e.preventDefault();
					autoFitTreeHierarchical();
				}
				break;
		}
	});
	</script>

	<?php require_once 'templates/footer.php'; ?>
