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

// Family tree rendering function
function renderFamilyTree($family_data) {
    $output = '<div class="family-tree-container">';
    
    if (empty($family_data['members'])) {
        $output .= '<div class="alert alert-info">No family members found. Add your first family member above!</div>';
        return $output;
    }
    
    // Build relationships map
    $relationships = [];
    if (isset($family_data['relationships']) && is_array($family_data['relationships'])) {
        foreach ($family_data['relationships'] as $rel) {
            if (isset($rel['member1_id'], $rel['member2_id'], $rel['relationship_type'])) {
                if (!isset($relationships[$rel['member1_id']])) {
                    $relationships[$rel['member1_id']] = [];
                }
                $relationships[$rel['member1_id']][] = [
                    'type' => $rel['relationship_type'],
                    'member_id' => $rel['member2_id']
                ];
            }
        }
    }
    
    // Find root members (those without parents)
    $members_with_parents = [];
    foreach ($relationships as $member_id => $rels) {
        foreach ($rels as $rel) {
            if (isset($rel['type']) && $rel['type'] === 'child') {
                $members_with_parents[] = $member_id;
            }
        }
    }
    
    $root_members = array_filter($family_data['members'], function($member) use ($members_with_parents) {
        return isset($member['id']) && !in_array($member['id'], $members_with_parents);
    });
    
    $output .= '<div class="family-tree">';
    foreach ($root_members as $root_member) {
        $output .= renderFamilyUnit($root_member, $family_data, $relationships);
    }
    $output .= '</div>';
    
    // Add zoom controls
    $output .= '<div class="tree-controls">
        <button onclick="zoomTree(1.1)"><i class="fas fa-search-plus"></i></button>
        <button onclick="zoomTree(0.9)"><i class="fas fa-search-minus"></i></button>
    </div>';
    
    $output .= '</div>';
    return $output;
}

// Helper function to render a family unit
function renderFamilyUnit($member, $family_data, $relationships) {
    $output = '<div class="family-unit">';
    
    // Find spouse if exists
    $spouse = null;
    if (isset($member['id']) && isset($relationships[$member['id']])) {
        foreach ($relationships[$member['id']] as $rel) {
            if (isset($rel['type'], $rel['member_id']) && $rel['type'] === 'spouse') {
                $spouse = findMemberById($family_data['members'], $rel['member_id']);
                break;
            }
        }
    }
    
    // Render member and spouse
    $output .= '<div class="spouses">';
    if (isset($member['id'])) {
        $output .= renderMemberCard($member);
    }
    if ($spouse) {
        $output .= renderMemberCard($spouse);
    }
    $output .= '</div>';
    
    // Find and render children
    $children = [];
    if (isset($member['id']) && isset($relationships[$member['id']])) {
        foreach ($relationships[$member['id']] as $rel) {
            if (isset($rel['type'], $rel['member_id']) && $rel['type'] === 'parent') {
                $child = findMemberById($family_data['members'], $rel['member_id']);
                if ($child) {
                    $children[] = $child;
                }
            }
        }
    }
    
    if (!empty($children)) {
        $output .= '<div class="children">';
        foreach ($children as $child) {
            $output .= renderFamilyUnit($child, $family_data, $relationships);
        }
        $output .= '</div>';
    }
    
    $output .= '</div>';
    return $output;
}

// Helper function to render a member card
function renderMemberCard($member) {
    if (!isset($member['first_name'], $member['last_name'])) {
        return '<div class="member-card"><div class="member-info"><h4>Invalid Member Data</h4></div></div>';
    }

    $gender = isset($member['gender']) ? strtolower($member['gender']) : '';
    $output = '<div class="member-card ' . htmlspecialchars($gender) . '">';
    $output .= '<div class="member-photo">';
    if (isset($member['photo_url']) && !empty($member['photo_url'])) {
        $output .= '<img src="' . htmlspecialchars($member['photo_url']) . '" alt="' . htmlspecialchars($member['first_name']) . '">';
    } else {
        $output .= '<div class="default-photo"><i class="fas fa-user"></i></div>';
    }
    $output .= '</div>';
    $output .= '<div class="member-info">';
    
    // Display name with maiden name for females
    $display_name = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
    if (isset($member['gender']) && $member['gender'] === 'F' && isset($member['maiden_name']) && !empty($member['maiden_name'])) {
        $display_name .= ' <small class="text-muted">(née ' . htmlspecialchars($member['maiden_name']) . ')</small>';
    }
    $output .= '<h4>' . $display_name . '</h4>';
    
    if (isset($member['date_of_birth']) && !empty($member['date_of_birth'])) {
        $output .= '<p><i class="fas fa-birthday-cake"></i> ' . htmlspecialchars($member['date_of_birth']);
        if (isset($member['date_of_death']) && !empty($member['date_of_death'])) {
            $output .= ' - ' . htmlspecialchars($member['date_of_death']);
        }
        $output .= '</p>';
    }
    if (isset($member['birth_place']) && !empty($member['birth_place'])) {
        $output .= '<p><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($member['birth_place']) . '</p>';
    }
    if (isset($member['occupation']) && !empty($member['occupation'])) {
        $output .= '<p><i class="fas fa-briefcase"></i> ' . htmlspecialchars($member['occupation']) . '</p>';
    }
    $output .= '</div>';
    $output .= '</div>';
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
