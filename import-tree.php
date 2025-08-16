<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
redirect_if_not_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('dashboard.php');
}

// Verify CSRF token
verify_csrf_token();

$db = new Database();
$conn = $db->getConnection();

try {
    // Start transaction
    $conn->beginTransaction();

    // Create new tree
    $tree_name = isset($_POST['tree_name']) ? sanitize_input($_POST['tree_name']) : 'Imported Tree';
    $tree_desc = isset($_POST['tree_description']) ? sanitize_input($_POST['tree_description']) : '';

    $stmt = $conn->prepare("
        INSERT INTO family_trees (name, description, owner_id, created_at, updated_at)
        VALUES (?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$tree_name, $tree_desc, $_SESSION['user_id']]);
    $tree_id = $conn->lastInsertId();

    if (!isset($_FILES['gedcom_file']) || $_FILES['gedcom_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No file uploaded or upload error occurred.");
    }

    // Read GEDCOM file
    $gedcom_content = file_get_contents($_FILES['gedcom_file']['tmp_name']);
    if ($gedcom_content === false) {
        throw new Exception("Could not read uploaded file.");
    }

    // Parse GEDCOM content
    $lines = explode("\n", $gedcom_content);
    $current_record = null;
    $current_level = -1;
    $individuals = [];
    $families = [];
    $current_individual = null;
    $current_family = null;

    foreach ($lines as $line) {
        $line = rtrim($line);
        if (empty($line)) continue;

        // Parse GEDCOM line
        if (!preg_match('/^(\d+)\s+(@[^@]+@)?\s*(\w+)(?:\s+(.*))?$/', $line, $matches)) {
            continue;
        }

        $level = (int)$matches[1];
        $xref = isset($matches[2]) ? trim($matches[2], '@') : '';
        $tag = $matches[3];
        $value = isset($matches[4]) ? $matches[4] : '';

        if ($level === 0) {
            // Save previous record if exists
            if ($current_record === 'INDI' && $current_individual) {
                $individuals[$current_individual['id']] = $current_individual;
            } elseif ($current_record === 'FAM' && $current_family) {
                $families[] = $current_family;
            }

            // Start new record
            if ($tag === 'INDI') {
                $current_record = 'INDI';
                $current_individual = [
                    'id' => $xref,
                    'first_name' => '',
                    'last_name' => '',
                    'gender' => '',
                    'birth_date' => null,
                    'birth_place' => '',
                    'death_date' => null,
                    'death_place' => '',
                    'notes' => ''
                ];
            } elseif ($tag === 'FAM') {
                $current_record = 'FAM';
                $current_family = [
                    'husband' => '',
                    'wife' => '',
                    'children' => [],
                    'marriage_date' => null
                ];
            } else {
                $current_record = $tag;
                $current_individual = null;
                $current_family = null;
            }
        } elseif ($current_record === 'INDI' && $current_individual) {
            // Process individual data
            if ($tag === 'NAME') {
                // Parse name parts
                if (preg_match('/([^\/]+)\/([^\/]+)\//', $value, $name_matches)) {
                    $current_individual['first_name'] = trim($name_matches[1]);
                    $current_individual['last_name'] = trim($name_matches[2]);
                } else {
                    $name_parts = explode(' ', $value);
                    $current_individual['last_name'] = array_pop($name_parts);
                    $current_individual['first_name'] = implode(' ', $name_parts);
                }
            } elseif ($tag === 'SEX') {
                $current_individual['gender'] = $value === 'M' ? 'male' : ($value === 'F' ? 'female' : 'other');
            } elseif ($level === 1 && $tag === 'BIRT') {
                $current_individual['birth_date'] = null; // Will be set by DATE tag
            } elseif ($level === 2 && $tag === 'DATE' && isset($current_individual['birth_date']) === null) {
                $current_individual['birth_date'] = parse_gedcom_date($value);
            } elseif ($level === 2 && $tag === 'PLAC' && isset($current_individual['birth_date']) === null) {
                $current_individual['birth_place'] = $value;
            } elseif ($level === 1 && $tag === 'DEAT') {
                $current_individual['death_date'] = null; // Will be set by DATE tag
            } elseif ($level === 2 && $tag === 'DATE' && isset($current_individual['death_date']) === null) {
                $current_individual['death_date'] = parse_gedcom_date($value);
            } elseif ($level === 2 && $tag === 'PLAC' && isset($current_individual['death_date']) === null) {
                $current_individual['death_place'] = $value;
            } elseif ($tag === 'NOTE') {
                $current_individual['notes'] .= $value . "\n";
            }
        } elseif ($current_record === 'FAM' && $current_family) {
            // Process family data
            if ($tag === 'HUSB') {
                $current_family['husband'] = trim($value, '@');
            } elseif ($tag === 'WIFE') {
                $current_family['wife'] = trim($value, '@');
            } elseif ($tag === 'CHIL') {
                $current_family['children'][] = trim($value, '@');
            } elseif ($level === 1 && $tag === 'MARR') {
                $current_family['marriage_date'] = null; // Will be set by DATE tag
            } elseif ($level === 2 && $tag === 'DATE' && isset($current_family['marriage_date']) === null) {
                $current_family['marriage_date'] = parse_gedcom_date($value);
            }
        }
    }

    // Save last record if exists
    if ($current_record === 'INDI' && $current_individual) {
        $individuals[$current_individual['id']] = $current_individual;
    } elseif ($current_record === 'FAM' && $current_family) {
        $families[] = $current_family;
    }

    // Insert individuals into database
    $person_map = []; // Maps GEDCOM IDs to database IDs
    foreach ($individuals as $indi) {
        $stmt = $conn->prepare("
            INSERT INTO people (
                tree_id, first_name, last_name, gender,
                date_of_birth, birth_place,
                date_of_death, death_place,
                notes, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, NOW(), NOW()
            )
        ");
        $stmt->execute([
            $tree_id,
            $indi['first_name'],
            $indi['last_name'],
            $indi['gender'],
            $indi['birth_date'],
            $indi['birth_place'],
            $indi['death_date'],
            $indi['death_place'],
            $indi['notes']
        ]);
        $person_map[$indi['id']] = $conn->lastInsertId();
    }

    // Insert relationships
    foreach ($families as $fam) {
        // Add spouse relationship
        if (!empty($fam['husband']) && !empty($fam['wife']) &&
            isset($person_map[$fam['husband']]) && isset($person_map[$fam['wife']])) {
            $stmt = $conn->prepare("
                INSERT INTO relationships (
                    tree_id, person1_id, person2_id,
                    relationship_type, marriage_date,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?,
                    'spouse', ?,
                    NOW(), NOW()
                )
            ");
            $stmt->execute([
                $tree_id,
                $person_map[$fam['husband']],
                $person_map[$fam['wife']],
                $fam['marriage_date']
            ]);
        }

        // Add parent-child relationships
        foreach ($fam['children'] as $child) {
            if (!isset($person_map[$child])) continue;

            if (!empty($fam['husband']) && isset($person_map[$fam['husband']])) {
                $stmt = $conn->prepare("
                    INSERT INTO relationships (
                        tree_id, person1_id, person2_id,
                        relationship_type, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?,
                        'parent-child', NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $tree_id,
                    $person_map[$fam['husband']],
                    $person_map[$child]
                ]);
            }

            if (!empty($fam['wife']) && isset($person_map[$fam['wife']])) {
                $stmt = $conn->prepare("
                    INSERT INTO relationships (
                        tree_id, person1_id, person2_id,
                        relationship_type, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?,
                        'parent-child', NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    $tree_id,
                    $person_map[$fam['wife']],
                    $person_map[$child]
                ]);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    $_SESSION['flash_message'] = "Family tree imported successfully!";
    $_SESSION['flash_type'] = "success";
    redirect_to('family-tree.php?id=' . $tree_id);

} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollBack();
    $_SESSION['flash_message'] = "Error importing family tree: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
    redirect_to('dashboard.php');
}

// Helper function to parse GEDCOM dates
function parse_gedcom_date($gedcom_date) {
    // Remove any @ symbols that might be present
    $gedcom_date = str_replace('@', '', trim($gedcom_date));
    
    // Convert month abbreviations to numbers
    $months = [
        'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
        'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
        'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
    ];
    
    // Try to parse various date formats
    if (preg_match('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i', $gedcom_date, $matches)) {
        return sprintf('%04d-%02d-%02d', $matches[3], $months[strtoupper($matches[2])], $matches[1]);
    } elseif (preg_match('/(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i', $gedcom_date, $matches)) {
        return sprintf('%04d-%02d-01', $matches[2], $months[strtoupper($matches[1])]);
    } elseif (preg_match('/(\d{4})/', $gedcom_date, $matches)) {
        return sprintf('%04d-01-01', $matches[1]);
    }
    
    return null;
}
