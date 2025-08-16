<?php
require_once 'config.php';
require_once 'RateLimit.php';

// Initialize rate limiter
$rateLimiter = new RateLimit();

// Rate limiting function
function check_rate_limit($endpoint = 'default') {
    global $rateLimiter;
    
    if (!$rateLimiter->check($endpoint)) {
        if (!headers_sent()) {
            http_response_code(429); // Too Many Requests
            $remaining = $rateLimiter->getTimeUntilReset($endpoint);
            
            header('X-RateLimit-Remaining: ' . $rateLimiter->getRemainingRequests($endpoint));
            header('X-RateLimit-Reset: ' . $remaining);
            header('Retry-After: ' . $remaining);
            
            if (is_ajax_request()) {
                echo json_encode([
                    'error' => 'Rate limit exceeded',
                    'message' => 'Please try again later',
                    'retry_after' => $remaining
                ]);
            } else {
                $_SESSION['flash_message'] = 'Too many requests. Please try again later.';
                $_SESSION['flash_type'] = 'warning';
                header('Location: ' . $_SERVER['HTTP_REFERER']);
            }
        }
        exit;
    }
}

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Redirect helper function
function redirect_to($path) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit();
}



function redirect_if_not_logged_in() {
    if (!is_logged_in()) {
        redirect_to('login.php');
        exit();
    }
}

// Tree access control function
function can_access_tree($tree_id) {
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

// Relationship functions
function get_relationship_type($relationship_id) {
    $types = [
        1 => 'Parent',
        2 => 'Child',
        3 => 'Sibling',
        4 => 'Spouse',
        5 => 'Grandparent',
        6 => 'Grandchild',
        7 => 'Aunt/Uncle',
        8 => 'Niece/Nephew',
    ];
    return isset($types[$relationship_id]) ? $types[$relationship_id] : 'Unknown';
}

// Dynamic multi-generation family tree rendering function
function renderFamilyTree($family_data) {
    $output = '<div class="family-tree-container">';
    
    if (empty($family_data['members'])) {
        $output .= '<div class="alert alert-info">No family members found. Add your first family member above!</div>';
        return $output . '</div>';
    }
    
    // Build comprehensive relationships map
    $relationships_map = buildRelationshipsMap($family_data);
    
    // Calculate generational structure
    $generational_tree = buildGenerationalStructure($family_data['members'], $relationships_map);
    
    if (empty($generational_tree)) {
        // If no clear structure, show all members
        $output .= '<div class="alert alert-warning">Complex family structure detected. Showing all members:</div>';
        $output .= renderSimpleList($family_data['members']);
    } else {
        $output .= '<div class="family-tree">';
        $output .= renderMultiGenerationalTree($generational_tree, $family_data, $relationships_map);
        $output .= '</div>';
    }
    
    // Add zoom controls
    $output .= '<div class="tree-controls">
        <button onclick="zoomTree(1.1)" title="Zoom In"><i class="fas fa-search-plus"></i></button>
        <button onclick="zoomTree(0.9)" title="Zoom Out"><i class="fas fa-search-minus"></i></button>
        <button onclick="resetZoom()" title="Reset Zoom"><i class="fas fa-expand-arrows-alt"></i></button>
        <button onclick="autoFitTree()" title="Fit to Screen"><i class="fas fa-compress-arrows-alt"></i></button>
    </div>';
    
    $output .= '</div>';
    return $output;
}

// Build comprehensive relationships map
function buildRelationshipsMap($family_data) {
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

// Build generational structure for unlimited generations
function buildGenerationalStructure($members, $relationships_map) {
    $generations = [];
    $member_generations = [];
    $processed = [];
    
    // Find all root members (those without parents)
    $roots = findRootGeneration($members, $relationships_map);
    
    if (empty($roots)) {
        return null; // Can't build structure
    }
    
    // Assign generation 0 to roots
    foreach ($roots as $root) {
        $member_generations[$root['id']] = 0;
        $generations[0][] = $root;
        $processed[$root['id']] = true;
    }
    
    // Process each generation level
    $current_generation = 0;
    $max_iterations = 20; // Safety limit
    $iterations = 0;
    
    while ($iterations < $max_iterations) {
        $iterations++;
        $found_children = false;
        
        // Find children of current generation
        if (isset($generations[$current_generation])) {
            foreach ($generations[$current_generation] as $parent) {
                $children = findChildren($parent['id'], $members, $relationships_map);
                
                foreach ($children as $child) {
                    if (!isset($processed[$child['id']])) {
                        $child_generation = $current_generation + 1;
                        $member_generations[$child['id']] = $child_generation;
                        $generations[$child_generation][] = $child;
                        $processed[$child['id']] = true;
                        $found_children = true;
                    }
                }
            }
        }
        
        if (!$found_children) {
            break; // No more children found
        }
        
        $current_generation++;
    }
    
    return $generations;
}

// Render the complete multi-generational tree
function renderMultiGenerationalTree($generational_tree, $family_data, $relationships_map) {
    $output = '';
    
    foreach ($generational_tree as $generation_level => $members) {
        $output .= renderDynamicGenerationLevel($members, $family_data, $relationships_map, $generation_level);
    }
    
    return $output;
}

// Render a generation level dynamically
function renderDynamicGenerationLevel($members, $family_data, $relationships_map, $level) {
    if (empty($members)) {
        return '';
    }
    
    $generation_names = [
        0 => 'Founders',
        1 => '1st Generation',
        2 => '2nd Generation', 
        3 => '3rd Generation',
        4 => '4th Generation',
        5 => '5th Generation'
    ];
    
    // Generate dynamic names for higher generations
    if ($level > 5) {
        $generation_names[$level] = $level . 'th Generation';
    }
    
    $output = '<div class="generation-level" data-level="' . $level . '">';
    
    // Add generation label
    $generation_name = $generation_names[$level] ?? 'Generation ' . $level;
    $output .= '<div class="generation-label">' . $generation_name . '</div>';
    
    // Group members into family units (married couples and singles)
    $family_units = buildFamilyUnits($members, $relationships_map);
    
    foreach ($family_units as $unit) {
        $output .= renderDynamicFamilyUnit($unit, $family_data, $relationships_map, $level);
    }
    
    $output .= '</div>';
    
    return $output;
}

// Build family units with spouses grouped together
function buildFamilyUnits($members, $relationships_map) {
    $units = [];
    $processed = [];
    
    foreach ($members as $member) {
        if (in_array($member['id'], $processed)) {
            continue;
        }
        
        $unit = [
            'parents' => [$member],
            'children' => [],
            'unit_id' => 'unit_' . $member['id']
        ];
        $processed[] = $member['id'];
        
        // Find all spouses
        $spouses = findSpouses($member['id'], $members, $relationships_map);
        foreach ($spouses as $spouse) {
            if (!in_array($spouse['id'], $processed)) {
                $unit['parents'][] = $spouse;
                $processed[] = $spouse['id'];
            }
        }
        
        // Find all children of this unit
        foreach ($unit['parents'] as $parent) {
            $children = findChildren($parent['id'], $members, $relationships_map);
            foreach ($children as $child) {
                // Avoid duplicates
                $child_exists = false;
                foreach ($unit['children'] as $existing_child) {
                    if ($existing_child['id'] == $child['id']) {
                        $child_exists = true;
                        break;
                    }
                }
                if (!$child_exists) {
                    $unit['children'][] = $child;
                }
            }
        }
        
        $units[] = $unit;
    }
    
    return $units;
}

// Find all spouses of a person
function findSpouses($person_id, $all_members, $relationships_map) {
    $spouses = [];
    
    if (!isset($relationships_map[$person_id])) {
        return $spouses;
    }
    
    foreach ($relationships_map[$person_id] as $rel) {
        if (in_array($rel['type'], ['husband', 'wife', 'spouse', 'partner'])) {
            $spouse = findMemberById($all_members, $rel['member_id']);
            if ($spouse) {
                $spouses[] = $spouse;
            }
        }
    }
    
    return $spouses;
}

// Render a dynamic family unit
function renderDynamicFamilyUnit($unit, $family_data, $relationships_map, $level) {
    $output = '<div class="family-unit" data-unit="' . $unit['unit_id'] . '">';
    
    // Render parents/spouses
    $parent_count = count($unit['parents']);
    $parent_class = $parent_count > 1 ? 'married-couple' : 'single-parent';
    
    $output .= '<div class="parent-level ' . $parent_class . '">';
    
    foreach ($unit['parents'] as $index => $parent) {
        $output .= renderMemberCard($parent);
        
        // Add marriage connection line for couples
        if ($parent_count > 1 && $index == 0) {
            $marriage_info = findMarriageInfo($unit['parents'][0]['id'], $unit['parents'][1]['id'], $relationships_map);
            $marriage_tooltip = '';
            if ($marriage_info) {
                $marriage_tooltip = 'title="Married: ' . 
                    ($marriage_info['marriage_date'] ? date('Y', strtotime($marriage_info['marriage_date'])) : 'Unknown') . 
                    ($marriage_info['marriage_place'] ? ' in ' . $marriage_info['marriage_place'] : '') . '"';
            }
            $output .= '<div class="marriage-line" ' . $marriage_tooltip . '></div>';
        }
    }
    
    $output .= '</div>';
    
    // Add connection line to children if they exist
    if (!empty($unit['children'])) {
        $output .= '<div class="parent-to-children-connector"></div>';
        
        // Render children connection area
        $child_count = count($unit['children']);
        $children_class = $child_count == 1 ? 'single-child' : 'multiple-children';
        
        $output .= '<div class="children-connector-area ' . $children_class . '">';
        $output .= '<div class="horizontal-connector"></div>';
        
        // Individual child connectors
        foreach ($unit['children'] as $index => $child) {
            $left_position = ($index / max(1, $child_count - 1)) * 100;
            if ($child_count == 1) $left_position = 50;
            
            $output .= '<div class="child-connector" style="left: ' . $left_position . '%;"></div>';
        }
        
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}

// Find marriage information between two people
function findMarriageInfo($person1_id, $person2_id, $relationships_map) {
    if (isset($relationships_map[$person1_id])) {
        foreach ($relationships_map[$person1_id] as $rel) {
            if ($rel['member_id'] == $person2_id && in_array($rel['type'], ['husband', 'wife', 'spouse'])) {
                return [
                    'marriage_date' => $rel['marriage_date'] ?? null,
                    'marriage_place' => $rel['marriage_place'] ?? null
                ];
            }
        }
    }
    return null;
}

// Find the root generation (oldest living generation or those without parents)
function findRootGeneration($members, $relationships_map) {
    $has_parents_set = [];
    $potential_roots = [];

    // Determine who has parents by scanning all edges:
    // - If someone has an outgoing son/daughter/child edge, that person is the child (has parents)
    // - If someone has an outgoing father/mother/parent edge, the target member is the child (has parents)
    foreach ($relationships_map as $person_id => $relationships) {
        foreach ($relationships as $rel) {
            if (in_array($rel['type'], ['son', 'daughter', 'child'])) {
                $has_parents_set[$person_id] = true; // person_id is the child
            }
            if (in_array($rel['type'], ['father', 'mother', 'parent'])) {
                $has_parents_set[$rel['member_id']] = true; // target has parents
            }
        }
    }

    // Root members are those without parents in the tree
    foreach ($members as $member) {
        if (empty($has_parents_set[$member['id']])) {
            $potential_roots[] = $member;
        }
    }

    // If no clear roots found, pick the oldest members
    if (empty($potential_roots)) {
        usort($members, function($a, $b) {
            $dateA = $a['date_of_birth'] ?? '9999-12-31';
            $dateB = $b['date_of_birth'] ?? '9999-12-31';
            return strcmp($dateA, $dateB);
        });
        return array_slice($members, 0, min(4, count($members)));
    }

    return $potential_roots;
}

// Group members into family units (couples + singles)
function groupIntoFamilyUnits($members, $relationships_map) {
    $units = [];
    $processed = [];
    
    foreach ($members as $member) {
        if (in_array($member['id'], $processed)) {
            continue;
        }
        
        $unit = ['parents' => [$member]];
        $processed[] = $member['id'];
        
        // Find spouse
        if (isset($relationships_map[$member['id']])) {
            foreach ($relationships_map[$member['id']] as $rel) {
                if (in_array($rel['type'], ['husband', 'wife', 'spouse']) && !in_array($rel['member_id'], $processed)) {
                    $spouse = findMemberById($members, $rel['member_id']);
                    if ($spouse) {
                        $unit['parents'][] = $spouse;
                        $processed[] = $spouse['id'];
                        break;
                    }
                }
            }
        }
        
        $units[] = $unit;
    }
    
    return $units;
}

// Render a single family unit
function renderFamilyUnit($unit, $family_data, $relationships_map, $level, $suppress_parent_ids = []) {
    $output = '<div class="family-unit">';
    
    // Render parents
    $parent_count = count($unit['parents']);
    $parent_class = $parent_count > 1 ? '' : 'single';
    
    $output .= '<div class="parent-level ' . $parent_class . '">';
    $multiple_parents = count($unit['parents']) > 1;
    foreach ($unit['parents'] as $parent) {
        // Suppress if this parent was just rendered as a child in the previous generation
        $suppress = in_array($parent['id'], $suppress_parent_ids);
        if ($suppress) {
            continue; // Avoid re-rendering the child when they appear as a parent in the next generation with a spouse
        }
        $output .= renderMemberCard($parent);
    }
    $output .= '</div>';
    
    // Find and render children
    $all_children = [];
    foreach ($unit['parents'] as $parent) {
        $children = findChildren($parent['id'], $family_data['members'], $relationships_map);
        $all_children = array_merge($all_children, $children);
    }
    
    // Remove duplicates
    $unique_children = [];
    $child_ids = [];
    foreach ($all_children as $child) {
        if (!in_array($child['id'], $child_ids)) {
            $unique_children[] = $child;
            $child_ids[] = $child['id'];
        }
    }
    
    if (!empty($unique_children)) {
        $output .= '<div class="parent-to-children-line"></div>';
        $child_class = count($unique_children) == 1 ? 'single-child' : '';
        
        $output .= '<div class="children-level ' . $child_class . '">';
        
        // Add connection points for each child
        $child_positions = [];
        $total_children = count($unique_children);
        
        foreach ($unique_children as $index => $child) {
            $position = ($index / max(1, $total_children - 1)) * 100;
            $child_positions[] = $position;
            
            $output .= '<div class="child-wrapper" style="position: relative;">';
            $output .= '<div class="child-connection" style="left: 50%; transform: translateX(-50%);"></div>';
            $output .= renderMemberCard($child);
            // Render this child's descendants inline (spouse + children) without reprinting the child card again
            // Pass suppress_parent_ids to avoid duplicating the child when rendering their unit
            $output .= renderGenerationLevel([$child], $family_data, $relationships_map, $level + 1, [$child['id']]);
            $output .= '</div>';
        }
        
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
}

// Find children of a specific parent
function findChildren($parent_id, $all_members, $relationships_map) {
    $children = [];
    
    if (!isset($relationships_map[$parent_id])) {
        return $children;
    }
    
    foreach ($relationships_map[$parent_id] as $rel) {
        // Parent -> Child relations are marked as father/mother/parent
        if (in_array($rel['type'], ['father', 'mother', 'parent'])) {
            $child = findMemberById($all_members, $rel['member_id']);
            if ($child) {
                $children[] = $child;
            }
        }
    }
    
    return $children;
}

// Render simple list when tree structure is too complex
function renderSimpleList($members) {
    $output = '<div class="simple-member-list">';
    foreach ($members as $member) {
        $output .= '<div class="simple-member-item">' . renderMemberCard($member) . '</div>';
    }
    $output .= '</div>';
    return $output;
}

// Enhanced helper function to render a member card
function renderMemberCard($member) {
    if (!isset($member['first_name'], $member['last_name'])) {
        return '<div class="member-card"><div class="member-info"><h4>Invalid Member Data</h4></div></div>';
    }

    $gender = isset($member['gender']) ? strtolower($member['gender']) : '';
    $gender_class = $gender === 'm' ? 'male' : ($gender === 'f' ? 'female' : 'other');
    
    // Check if deceased
    $is_deceased = !empty($member['date_of_death']) || (isset($member['is_living']) && !$member['is_living']);
    $deceased_class = $is_deceased ? ' deceased' : '';
    
    $output = '<div class="member-card ' . $gender_class . $deceased_class . '" data-member-id="' . $member['id'] . '">';
    
    // Photo section
    $output .= '<div class="member-photo">';
    if (isset($member['photo_url']) && !empty($member['photo_url']) && file_exists($member['photo_url'])) {
        $output .= '<img src="' . htmlspecialchars($member['photo_url']) . '" alt="' . htmlspecialchars($member['first_name']) . '">';
    } else {
        $icon = $gender === 'f' ? 'fa-female' : ($gender === 'm' ? 'fa-male' : 'fa-user');
        $output .= '<div class="default-photo"><i class="fas ' . $icon . '"></i></div>';
    }
    $output .= '</div>';
    
    $output .= '<div class="member-info">';
    
    // Name with maiden name for females
    $display_name = htmlspecialchars($member['first_name']);
    if (isset($member['middle_name']) && !empty($member['middle_name'])) {
        $display_name .= ' ' . htmlspecialchars($member['middle_name']);
    }
    $display_name .= ' ' . htmlspecialchars($member['last_name']);
    
    if ($gender === 'f' && isset($member['maiden_name']) && !empty($member['maiden_name'])) {
        $display_name .= '<br><small class="text-muted">(née ' . htmlspecialchars($member['maiden_name']) . ')</small>';
    }
    
    $output .= '<div class="member-name">' . $display_name . '</div>';
    
    // Dates
    $dates = '';
    if (isset($member['date_of_birth']) && !empty($member['date_of_birth'])) {
        $birth_date = date('Y', strtotime($member['date_of_birth']));
        $dates = $birth_date;
        
        if ($is_deceased && isset($member['date_of_death']) && !empty($member['date_of_death'])) {
            $death_date = date('Y', strtotime($member['date_of_death']));
            $dates .= ' - ' . $death_date;
        } elseif (!$is_deceased) {
            $dates .= ' - Present';
        }
    }
    
    if ($dates) {
        $output .= '<div class="member-dates">(' . $dates . ')</div>';
    }
    
    // Additional info
    if (isset($member['birth_place']) && !empty($member['birth_place'])) {
        $output .= '<p><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($member['birth_place']) . '</p>';
    }
    
    if (isset($member['occupation']) && !empty($member['occupation'])) {
        $output .= '<p><i class="fas fa-briefcase"></i> ' . htmlspecialchars($member['occupation']) . '</p>';
    }
    
    $output .= '</div>'; // member-info
    $output .= '</div>'; // member-card
    
    return $output;
}

// Helper function to find a member by ID
function findMemberById($members, $id) {
    foreach ($members as $member) {
        if ($member['id'] == $id) {
            return $member;
        }
    }
    return null;
}

// Enhanced function to detect and handle complex family structures
function detectComplexStructures($members, $relationships_map) {
    $issues = [];
    
    // Check for multiple marriages
    foreach ($relationships_map as $person_id => $relationships) {
        $spouse_count = 0;
        foreach ($relationships as $rel) {
            if (in_array($rel['type'], ['husband', 'wife', 'spouse'])) {
                $spouse_count++;
            }
        }
        if ($spouse_count > 1) {
            $member = findMemberById($members, $person_id);
            $issues[] = [
                'type' => 'multiple_marriages',
                'member' => $member,
                'count' => $spouse_count
            ];
        }
    }
    
    // Check for potential cycles in family tree
    $visited = [];
    foreach ($members as $member) {
        if (!isset($visited[$member['id']])) {
            if (detectCycle($member['id'], $relationships_map, $visited, [])) {
                $issues[] = [
                    'type' => 'circular_reference',
                    'member' => $member
                ];
            }
        }
    }
    
    return $issues;
}

// Detect circular references in family relationships
function detectCycle($person_id, $relationships_map, &$visited, $path) {
    if (in_array($person_id, $path)) {
        return true; // Cycle detected
    }
    
    if (isset($visited[$person_id])) {
        return false; // Already processed
    }
    
    $visited[$person_id] = true;
    $path[] = $person_id;
    
    if (isset($relationships_map[$person_id])) {
        foreach ($relationships_map[$person_id] as $rel) {
            if (in_array($rel['type'], ['father', 'mother', 'parent'])) {
                if (detectCycle($rel['member_id'], $relationships_map, $visited, $path)) {
                    return true;
                }
            }
        }
    }
    
    array_pop($path);
    return false;
}

// Generate tree statistics
function generateTreeStatistics($family_data) {
    $stats = [
        'total_members' => count($family_data['members']),
        'total_relationships' => count($family_data['relationships']) / 2, // Divide by 2 for bidirectional
        'generations' => 0,
        'living_members' => 0,
        'deceased_members' => 0,
        'males' => 0,
        'females' => 0,
        'others' => 0,
        'marriages' => 0,
        'average_children_per_family' => 0
    ];
    
    foreach ($family_data['members'] as $member) {
        // Count by living status
        if (empty($member['date_of_death']) && ($member['is_living'] ?? true)) {
            $stats['living_members']++;
        } else {
            $stats['deceased_members']++;
        }
        
        // Count by gender
        switch (strtoupper($member['gender'] ?? '')) {
            case 'M':
                $stats['males']++;
                break;
            case 'F':
                $stats['females']++;
                break;
            default:
                $stats['others']++;
                break;
        }
    }
    
    // Count marriages
    foreach ($family_data['relationships'] as $rel) {
        if (in_array($rel['relationship_subtype'], ['husband', 'wife', 'spouse'])) {
            $stats['marriages']++;
        }
    }
    $stats['marriages'] = $stats['marriages'] / 2; // Divide by 2 for bidirectional
    
    return $stats;
}

// Export tree data as JSON for external use
function exportTreeAsJSON($family_data) {
    return json_encode([
        'version' => '2.0',
        'exported_at' => date('Y-m-d H:i:s'),
        'members' => $family_data['members'],
        'relationships' => $family_data['relationships'],
        'statistics' => generateTreeStatistics($family_data)
    ], JSON_PRETTY_PRINT);
}

// Search members by various criteria
function searchMembers($family_data, $criteria) {
    $results = [];
    
    foreach ($family_data['members'] as $member) {
        $match = true;
        
        if (isset($criteria['name']) && !empty($criteria['name'])) {
            $full_name = trim($member['first_name'] . ' ' . ($member['middle_name'] ?? '') . ' ' . $member['last_name']);
            if (stripos($full_name, $criteria['name']) === false) {
                $match = false;
            }
        }
        
        if (isset($criteria['gender']) && !empty($criteria['gender'])) {
            if (strtoupper($member['gender']) !== strtoupper($criteria['gender'])) {
                $match = false;
            }
        }
        
        if (isset($criteria['birth_year']) && !empty($criteria['birth_year'])) {
            if (empty($member['date_of_birth']) || 
                date('Y', strtotime($member['date_of_birth'])) != $criteria['birth_year']) {
                $match = false;
            }
        }
        
        if (isset($criteria['occupation']) && !empty($criteria['occupation'])) {
            if (empty($member['occupation']) || 
                stripos($member['occupation'], $criteria['occupation']) === false) {
                $match = false;
            }
        }
        
        if ($match) {
            $results[] = $member;
        }
    }
    
    return $results;
}
