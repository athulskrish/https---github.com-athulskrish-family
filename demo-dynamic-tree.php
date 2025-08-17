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
			
			foreach ($family_units as $unitIndex => $unit) {
				// Find children of this family unit for connection tracking
				$children_in_next_gen = [];
				if (isset($generations[$level + 1])) {
					foreach ($unit['members'] as $parent) {
						$parent_children = findChildrens($parent['id'], $generations[$level + 1], $relationships_map);
						$children_in_next_gen = array_merge($children_in_next_gen, $parent_children);
					}
				}
				
				$has_children = !empty($children_in_next_gen);
				$unit_class = $has_children ? 'has-children' : '';
				
				$output .= '<div class="family-unit-inline ' . $unit_class . '" data-unit="' . $level . '-' . $unitIndex . '">';
				
				// Render all members in this unit (spouses together)
				foreach ($unit['members'] as $index => $member) {
					$output .= renderEnhancedMemberCard($member);
					
					// Add marriage connector between spouses
					if ($index < count($unit['members']) - 1) {
						$output .= '<div class="marriage-connector-inline"></div>';
					}
				}
				
				// Add connection point for children if this unit has children
				if ($has_children) {
					$child_names = array_map(function($child) { return $child['first_name']; }, $children_in_next_gen);
					$output .= '<div class="parent-connection-point" data-children-count="' . count($children_in_next_gen) . '" title="Children: ' . implode(', ', $child_names) . '"></div>';
				}
				
				$output .= '</div>';
			}
			
			$output .= '</div>';
			
			// Add connecting lines to next generation with specific parent-child mapping
			if (isset($generations[$level + 1])) {
				$output .= renderParentChildConnectors($generations[$level], $generations[$level + 1], $relationships_map, $level);
			}
		}
		
		return $output;
	}

	// Render specific parent-child connecting lines
	function renderParentChildConnectors($parent_generation, $child_generation, $relationships_map, $level) {
		$output = '<div class="parent-child-connectors" data-from-level="' . $level . '">';
		
		// Group parents by family units
		$parent_units = groupMembersIntoFamilyUnits($parent_generation, $relationships_map);
		$child_units = groupMembersIntoFamilyUnits($child_generation, $relationships_map);
		
		$connector_lines = [];
		$unit_index = 0;
		
		foreach ($parent_units as $parent_unit) {
			$parent_children = [];
			
			// Find all children of this parent unit
			foreach ($parent_unit['members'] as $parent) {
				$children = findChildrens($parent['id'], $child_generation, $relationships_map);
				foreach ($children as $child) {
					if (!in_array($child['id'], array_column($parent_children, 'id'))) {
						$parent_children[] = $child;
					}
				}
			}
			
			if (!empty($parent_children)) {
				// Create connector line from this parent unit to its children
				$child_positions = [];
				$child_unit_index = 0;
				
				foreach ($child_units as $child_unit) {
					foreach ($child_unit['members'] as $child) {
						if (in_array($child['id'], array_column($parent_children, 'id'))) {
							$child_positions[] = $child_unit_index;
						}
					}
					$child_unit_index++;
				}
				
				if (!empty($child_positions)) {
					$output .= '<div class="family-connection-line" ';
					$output .= 'data-parent-unit="' . $unit_index . '" ';
					$output .= 'data-child-units="' . implode(',', array_unique($child_positions)) . '" ';
					$output .= 'data-children-count="' . count($parent_children) . '">';
					
					// Vertical drop line from parent
					$output .= '<div class="parent-drop-line"></div>';
					
					// Horizontal distribution line
					if (count(array_unique($child_positions)) > 1) {
						$output .= '<div class="child-distribution-line"></div>';
					}
					
					// Vertical lines to each child unit
					foreach (array_unique($child_positions) as $child_pos) {
						$output .= '<div class="child-connect-line" data-child-position="' . $child_pos . '"></div>';
					}
					
					$output .= '</div>';
				}
			}
			
			$unit_index++;
		}
		
		$output .= '</div>';
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
				
				<!-- Debug: Family data and relationships -->
				<div class="debug-info" style="background: rgba(0,0,0,0.8); color: white; padding: 20px; margin: 20px 0; border-radius: 10px; font-family: monospace; display: none;" id="debug-panel">
					<h4>Debug Information</h4>
					<div class="row">
						<div class="col-md-6">
							<h5>Family Members:</h5>
							<ul style="max-height: 200px; overflow-y: auto;">
								<?php foreach($family_data['members'] as $member): ?>
									<li>ID: <?php echo $member['id']; ?> - <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?> (<?php echo $member['gender']; ?>)</li>
								<?php endforeach; ?>
							</ul>
						</div>
						<div class="col-md-6">
							<h5>Relationships:</h5>
							<ul style="max-height: 200px; overflow-y: auto;">
								<?php foreach($family_data['relationships'] as $rel): ?>
									<li>
										<?php echo $rel['person1_id']; ?> (<?php echo $rel['person1_name'] ?? 'Unknown'; ?>) 
										→ <?php echo $rel['relationship_subtype']; ?> → 
										<?php echo $rel['person2_id']; ?> (<?php echo $rel['person2_name'] ?? 'Unknown'; ?>)
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					</div>
					<button onclick="document.getElementById('debug-panel').style.display='none'" class="btn btn-sm btn-secondary mt-2">Hide Debug</button>
				</div>
				
				<!-- Toggle Debug Button -->
				<button onclick="document.getElementById('debug-panel').style.display='block'" class="btn btn-outline-info mb-3">Show Debug Info</button>
				
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
					<button onclick="initializeConnectionLines()" title="Refresh Connections" style="background: linear-gradient(145deg, #e74c3c, #c0392b); border-color: #e74c3c;">
						<i class="fas fa-project-diagram"></i>
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
			
			// Recalculate connection lines after zoom
			setTimeout(initializeConnectionLines, 100);
		}
	}

	function resetZoomHierarchical() {
		hierarchicalZoom = 1;
		const container = document.querySelector('.hierarchical-tree-container');
		if (container) {
			container.style.transform = 'scale(1)';
			// Recalculate connection lines after reset
			setTimeout(initializeConnectionLines, 100);
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
			
			// Recalculate connection lines after auto-fit
			setTimeout(initializeConnectionLines, 200);
		}
	}

	// Initialize tree interactions
	document.addEventListener('DOMContentLoaded', function() {
		console.log('DOM Content Loaded - Starting family tree initialization');
		
		// Log DOM structure for debugging
		const container = document.querySelector('.hierarchical-tree-container');
		const generations = document.querySelectorAll('.generation-row');
		const connectors = document.querySelectorAll('.parent-child-connectors');
		const familyCards = document.querySelectorAll('.enhanced-member-card');
		
		console.log('DOM Elements Found:');
		console.log('- Container:', container ? 'Found' : 'Missing');
		console.log('- Generations:', generations.length);
		console.log('- Connector containers:', connectors.length);
		console.log('- Family cards:', familyCards.length);
		
		// Log each generation and its members
		generations.forEach((gen, index) => {
			const cards = gen.querySelectorAll('.enhanced-member-card');
			console.log(`Generation ${index}:`, cards.length, 'members');
			cards.forEach(card => {
				const id = card.dataset.memberId;
				const name = card.querySelector('.member-name')?.textContent || 'Unknown';
				console.log(`  - ID: ${id}, Name: ${name}`);
			});
		});
		
		if (container) {
			// Initialize connection lines
			initializeConnectionLines();
			
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
		
		// Auto-fit on load and initialize connections with multiple attempts
		setTimeout(() => {
			autoFitTreeHierarchical();
			// Multiple attempts to ensure layout is complete
			let attempts = 0;
			const maxAttempts = 3;
			
			function tryInitializeConnections() {
				attempts++;
				console.log(`Attempting to initialize connections (attempt ${attempts}/${maxAttempts})`);
				
				const connectionsContainer = document.querySelector('.parent-child-connectors');
				const familyCards = document.querySelectorAll('.enhanced-member-card');
				
				if (connectionsContainer && familyCards.length > 0) {
					initializeConnectionLines();
					console.log('Family tree connections initialized successfully');
				} else if (attempts < maxAttempts) {
					console.log('DOM not ready, retrying in 500ms...');
					setTimeout(tryInitializeConnections, 500);
				} else {
					console.log('Failed to initialize connections after maximum attempts');
				}
			}
			
			setTimeout(tryInitializeConnections, 300);
		}, 800);
	});

	// Initialize and position connection lines dynamically
	function initializeConnectionLines() {
		console.log('Starting connection line initialization...');
		
		const generations = document.querySelectorAll('.generation-row');
		const container = document.querySelector('.hierarchical-tree-container');
		
		if (!container) {
			console.log('No container found');
			return;
		}
		
		// Get relationship data from PHP (passed to JavaScript)
		const familyData = <?php echo json_encode($family_data); ?>;
		const relationships = familyData.relationships || [];
		
		console.log('Family data received by JavaScript:', familyData);
		console.log('Relationships received:', relationships.length);
		console.log('First 5 relationships:', relationships.slice(0, 5));
		
		// Verify specific relationships we expect
		const testRelationships = [
			{ parent: 1, child: 3, type: 'father' },  // Rajendra -> Sheeja
			{ parent: 3, child: 6, type: 'mother' },  // Sheeja -> Athul
			{ parent: 3, child: 7, type: 'mother' },  // Sheeja -> Amal
		];
		
		testRelationships.forEach(test => {
			// Check both directions: Parent->Child and Child->Parent
			const found = relationships.find(rel => {
				// Direction 1: Parent -> Child (father, mother, parent)
				const parentToChild = rel.person1_id == test.parent && 
				                     rel.person2_id == test.child && 
				                     rel.relationship_subtype === test.type;
				
				// Direction 2: Child -> Parent (son, daughter, child)  
				const childToParent = rel.person1_id == test.child && 
				                     rel.person2_id == test.parent && 
				                     ['son', 'daughter', 'child'].includes(rel.relationship_subtype);
				
				return parentToChild || childToParent;
			});
			console.log(`Test relationship ${test.parent}<->${test.child} (${test.type}):`, found ? '✅ Found' : '❌ Missing');
		});
		
		let connectionsCreated = 0;
		
		generations.forEach((generation, levelIndex) => {
			const nextGeneration = generations[levelIndex + 1];
			if (!nextGeneration) {
				console.log(`No next generation found for level ${levelIndex}`);
				return;
			}
			
			console.log(`Processing generation ${levelIndex} to ${levelIndex + 1}`);
			
			const parentUnits = generation.querySelectorAll('.family-unit-inline');
			const childUnits = nextGeneration.querySelectorAll('.family-unit-inline');
			const connectorContainer = generation.nextElementSibling;
			
			console.log(`Found ${parentUnits.length} parent units and ${childUnits.length} child units`);
			
			if (!connectorContainer) {
				console.log('❌ No connector container (nextElementSibling) found for level', levelIndex);
				return;
			}
			
			if (!connectorContainer.classList.contains('parent-child-connectors')) {
				console.log('❌ Next element is not a parent-child-connectors:', connectorContainer.className);
				return;
			}
			
			console.log('✅ Connector container found:', connectorContainer);
			
			// Clear existing connection lines
			connectorContainer.innerHTML = '';
			
			// Calculate positions and create connection lines
			parentUnits.forEach((parentUnit, parentIndex) => {
				console.log(`\n--- Processing parent unit ${parentIndex} ---`);
				
				// Get parent member IDs from this unit
				const parentCards = parentUnit.querySelectorAll('.enhanced-member-card');
				const parentIds = Array.from(parentCards).map(card => {
					const id = parseInt(card.dataset.memberId);
					const name = card.querySelector('.member-name')?.textContent || 'Unknown';
					console.log(`Parent in unit: ID=${id}, Name=${name}`);
					return id;
				});
				
				console.log('All parent IDs in unit:', parentIds);
				
				// Find child units that have members who are children of these parents
				const childPositions = [];
				const childRelationships = []; // Track which children belong to which parents
				
				childUnits.forEach((childUnit, childIndex) => {
					console.log(`  Checking child unit ${childIndex}:`);
					const childCards = childUnit.querySelectorAll('.enhanced-member-card');
					let belongsToParent = false;
					let childDetails = [];
					
					childCards.forEach(childCard => {
						const childId = parseInt(childCard.dataset.memberId);
						const childName = childCard.querySelector('.member-name')?.textContent || 'Unknown';
						console.log(`    Child: ID=${childId}, Name=${childName}`);
						
						// Check if any parent in this unit is a parent of this child
						parentIds.forEach(parentId => {
							console.log(`      Checking relationship ${parentId} -> ${childId}`);
							
							// Check both directions: Parent->Child and Child->Parent
							const parentChildRel = relationships.find(rel => {
								// Direction 1: Parent -> Child (father, mother, parent)
								const parentToChild = rel.person1_id == parentId && 
								                     rel.person2_id == childId && 
								                     ['father', 'mother', 'parent'].includes(rel.relationship_subtype);
								
								// Direction 2: Child -> Parent (son, daughter, child)
								const childToParent = rel.person1_id == childId && 
								                     rel.person2_id == parentId && 
								                     ['son', 'daughter', 'child'].includes(rel.relationship_subtype);
								
								const matches = parentToChild || childToParent;
								
								if (matches) {
									console.log(`      ✅ FOUND MATCH: ${parentId} <-> ${childId} (${rel.relationship_subtype}) [${parentToChild ? 'Parent->Child' : 'Child->Parent'}]`);
								}
								return matches;
							});
							
							if (parentChildRel) {
								belongsToParent = true;
								childDetails.push({
									childId: childId,
									childName: childName,
									parentId: parentId,
									relationship: parentChildRel.relationship_subtype
								});
								console.log(`      ✅ Relationship confirmed: ${parentId} <-> ${childId} (${parentChildRel.relationship_subtype}) - ${childName}`);
							}
						});
					});
					
					if (belongsToParent) {
						console.log(`    ✅ Child unit ${childIndex} belongs to parent unit ${parentIndex}`);
						const childRect = childUnit.getBoundingClientRect();
						const connectorRect = connectorContainer.getBoundingClientRect();
						const childLeft = childRect.left - connectorRect.left + (childRect.width / 2);
						childPositions.push({ 
							index: childIndex, 
							left: childLeft,
							details: childDetails
						});
						console.log(`    Position calculated: ${childLeft}px`);
					} else {
						console.log(`    ❌ Child unit ${childIndex} does not belong to parent unit ${parentIndex}`);
					}
				});
				
				console.log(`Final result: Found ${childPositions.length} child positions for parent unit ${parentIndex}`);
				
				if (childPositions.length === 0) {
					console.log(`❌ No children found for parent unit ${parentIndex}, skipping...`);
					return;
				}
				
				// Get parent position
				const parentRect = parentUnit.getBoundingClientRect();
				const connectorRect = connectorContainer.getBoundingClientRect();
				const parentLeft = parentRect.left - connectorRect.left + (parentRect.width / 2);
				
				console.log(`Creating connection from parent at ${parentLeft}px to ${childPositions.length} children`);
				
				// Create connection line structure
				const connectionLine = document.createElement('div');
				connectionLine.className = 'dynamic-family-connection';
				connectionLine.style.position = 'absolute';
				connectionLine.style.width = '100%';
				connectionLine.style.height = '100%';
				connectionLine.style.pointerEvents = 'none';
				
				// Vertical drop from parent
				const parentDrop = document.createElement('div');
				parentDrop.style.position = 'absolute';
				parentDrop.style.left = parentLeft - 3 + 'px';
				parentDrop.style.top = '0px';
				parentDrop.style.width = '6px';
				parentDrop.style.height = '25px';
				parentDrop.style.background = 'linear-gradient(to bottom, #e74c3c, #c0392b)'; // Make it red for visibility
				parentDrop.style.borderRadius = '3px';
				parentDrop.style.boxShadow = '0 3px 12px rgba(231, 76, 60, 0.6), 0 0 20px rgba(231, 76, 60, 0.3)';
				parentDrop.style.zIndex = '15';
				
				connectionLine.appendChild(parentDrop);
				
				if (childPositions.length > 1) {
					// Horizontal distribution line
					const minChildLeft = Math.min(...childPositions.map(c => c.left));
					const maxChildLeft = Math.max(...childPositions.map(c => c.left));
					
					const distributionLine = document.createElement('div');
					distributionLine.style.position = 'absolute';
					distributionLine.style.left = minChildLeft - 3 + 'px';
					distributionLine.style.top = '25px';
					distributionLine.style.width = (maxChildLeft - minChildLeft + 6) + 'px';
					distributionLine.style.height = '6px';
					distributionLine.style.background = 'linear-gradient(90deg, #e74c3c, #c0392b)';
					distributionLine.style.borderRadius = '3px';
					distributionLine.style.boxShadow = '0 3px 12px rgba(231, 76, 60, 0.6), 0 0 20px rgba(231, 76, 60, 0.3)';
					distributionLine.style.zIndex = '14';
					
					connectionLine.appendChild(distributionLine);
				}
				
				// Vertical lines to each child
				childPositions.forEach(childPos => {
					const childConnect = document.createElement('div');
					childConnect.style.position = 'absolute';
					childConnect.style.left = childPos.left - 3 + 'px';
					childConnect.style.top = '25px';
					childConnect.style.width = '6px';
					childConnect.style.height = '55px';
					childConnect.style.background = 'linear-gradient(to bottom, #e74c3c, #c0392b)';
					childConnect.style.borderRadius = '3px';
					childConnect.style.boxShadow = '0 3px 12px rgba(231, 76, 60, 0.6), 0 0 20px rgba(231, 76, 60, 0.3)';
					childConnect.style.zIndex = '15';
					
					connectionLine.appendChild(childConnect);
				});
				
				connectorContainer.appendChild(connectionLine);
				connectionsCreated++;
				
				console.log(`Created connection ${connectionsCreated} for parent unit ${parentIndex}`);
			});
		});
		
		console.log(`Connection line initialization complete. Created ${connectionsCreated} connections.`);
		
		// Add a visual indicator that connections are being processed
		if (connectionsCreated > 0) {
			console.log('✅ Connections successfully created!');
			
			// Add a success message to the page
			const successMsg = document.createElement('div');
			successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #27ae60; color: white; padding: 10px 20px; border-radius: 5px; z-index: 1000; font-weight: bold;';
			successMsg.textContent = `✅ ${connectionsCreated} family connections created!`;
			document.body.appendChild(successMsg);
			setTimeout(() => successMsg.remove(), 3000);
		} else {
			console.log('❌ No connections were created. Check relationship data.');
			
			// Add a simple test connection to verify DOM manipulation works
			console.log('🔧 Adding test connection to verify DOM access...');
			const firstConnector = document.querySelector('.parent-child-connectors');
			if (firstConnector) {
				const testLine = document.createElement('div');
				testLine.style.cssText = `
					position: absolute;
					top: 10px;
					left: 50%;
					width: 8px;
					height: 60px;
					background: linear-gradient(to bottom, #ff0000, #cc0000);
					border-radius: 4px;
					box-shadow: 0 0 15px rgba(255, 0, 0, 0.8);
					z-index: 20;
					transform: translateX(-50%);
				`;
				firstConnector.appendChild(testLine);
				console.log('✅ Test line added successfully - DOM manipulation works!');
				
				// Add an info message
				const infoMsg = document.createElement('div');
				infoMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #f39c12; color: white; padding: 10px 20px; border-radius: 5px; z-index: 1000; font-weight: bold;';
				infoMsg.textContent = '🔧 Test connection added - check console for details';
				document.body.appendChild(infoMsg);
				setTimeout(() => infoMsg.remove(), 5000);
			} else {
				console.log('❌ No connector containers found - DOM structure issue');
			}
			
			// Add an error message to the page
			const errorMsg = document.createElement('div');
			errorMsg.style.cssText = 'position: fixed; top: 70px; right: 20px; background: #e74c3c; color: white; padding: 10px 20px; border-radius: 5px; z-index: 1000; font-weight: bold;';
			errorMsg.textContent = '❌ No family connections found!';
			document.body.appendChild(errorMsg);
			setTimeout(() => errorMsg.remove(), 5000);
		}
	}

	// Debug function to test connection logic
	window.debugConnections = function() {
		console.log('=== DEBUG CONNECTION LOGIC ===');
		const familyData = <?php echo json_encode($family_data); ?>;
		const relationships = familyData.relationships || [];
		
		console.log('All relationships:', relationships);
		
		// Test each generation pair
		const generations = document.querySelectorAll('.generation-row');
		generations.forEach((gen, index) => {
			const nextGen = generations[index + 1];
			if (nextGen) {
				console.log(`\nGeneration ${index} -> ${index + 1}:`);
				const parentCards = gen.querySelectorAll('.enhanced-member-card');
				const childCards = nextGen.querySelectorAll('.enhanced-member-card');
				
				parentCards.forEach(parentCard => {
					const parentId = parseInt(parentCard.dataset.memberId);
					const parentName = parentCard.querySelector('.member-name').textContent;
					console.log(`  Parent: ${parentId} - ${parentName}`);
					
					childCards.forEach(childCard => {
						const childId = parseInt(childCard.dataset.memberId);
						const childName = childCard.querySelector('.member-name').textContent;
						
						const rel = relationships.find(r => 
							r.person1_id === parentId && 
							r.person2_id === childId && 
							['father', 'mother', 'parent'].includes(r.relationship_subtype)
						);
						
						if (rel) {
							console.log(`    -> Child: ${childId} - ${childName} (${rel.relationship_subtype})`);
						}
					});
				});
			}
		});
		
		// Now run the actual connection function
		initializeConnectionLines();
	}

	// Simple test function to create basic connections
	window.createTestConnection = function() {
		console.log('Creating test connection...');
		const connectors = document.querySelectorAll('.parent-child-connectors');
		console.log('Found', connectors.length, 'connector containers');
		
		connectors.forEach((connector, index) => {
			// Clear existing content
			connector.innerHTML = '';
			
			// Add a simple red line
			const testLine = document.createElement('div');
			testLine.style.cssText = `
				position: absolute;
				top: 0;
				left: 50%;
				width: 6px;
				height: 80px;
				background: linear-gradient(to bottom, #e74c3c, #c0392b);
				border-radius: 3px;
				box-shadow: 0 0 15px rgba(231, 76, 60, 0.8);
				z-index: 20;
				transform: translateX(-50%);
			`;
			
			connector.appendChild(testLine);
			console.log(`Added test line to connector ${index}`);
		});
		
		return connectors.length;
	}

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
