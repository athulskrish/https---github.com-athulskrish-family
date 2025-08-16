<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
// Check if user is logged in
redirect_if_not_logged_in();

$tree_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = isset($_GET['format']) ? sanitize_input($_GET['format']) : 'gedcom';

if (!$tree_id) {
    $_SESSION['flash_message'] = "Invalid family tree ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Verify user has access to this tree
$stmt = $conn->prepare("
    SELECT ft.*, 
           CASE 
               WHEN ft.owner_id = ? THEN 'owner'
               WHEN ts.permission_level IS NOT NULL THEN ts.permission_level
               WHEN ft.privacy_level = 'public' THEN 'view'
               ELSE NULL
           END as access_level
    FROM family_trees ft
    LEFT JOIN tree_sharing ts ON ft.id = ts.tree_id AND ts.user_id = ?
    WHERE ft.id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $tree_id]);
$tree = $stmt->fetch();

if (!$tree || !$tree['access_level']) {
    $_SESSION['flash_message'] = "You don't have access to this family tree.";
    $_SESSION['flash_type'] = "danger";
    header("Location: dashboard.php");
    exit();
}

// Get all members of the tree
$stmt = $conn->prepare("
    SELECT * FROM people WHERE tree_id = ?
");
$stmt->execute([$tree_id]);
$members = $stmt->fetchAll();

// Get all relationships
$stmt = $conn->prepare("
    SELECT r.*, p1.first_name as p1_first_name, p1.last_name as p1_last_name,
           p2.first_name as p2_first_name, p2.last_name as p2_last_name
    FROM relationships r
    JOIN people p1 ON r.person1_id = p1.id
    JOIN people p2 ON r.person2_id = p2.id
    WHERE p1.tree_id = ? AND p2.tree_id = ?
");
$stmt->execute([$tree_id, $tree_id]);
$relationships = $stmt->fetchAll();

if ($format === 'gedcom') {
    // Generate GEDCOM file
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . sanitize_input($tree['name']) . '.ged"');

    // GEDCOM header
    echo "0 HEAD\n";
    echo "1 CHAR UTF-8\n";
    echo "1 SOUR Family Tree Maker\n";
    echo "1 GEDC\n";
    echo "2 VERS 5.5.1\n";
    echo "2 FORM LINEAGE-LINKED\n";
    echo "1 DATE " . date('d M Y') . "\n";
    
    // Tree information
    echo "0 @T1@ FAM\n";
    echo "1 NAME " . gedcom_escape($tree['name']) . "\n";
    if ($tree['description']) {
        echo "1 NOTE " . gedcom_escape($tree['description']) . "\n";
    }

    // Export individuals
    foreach ($members as $member) {
        echo "\n0 @I" . $member['id'] . "@ INDI\n";
        echo "1 NAME " . gedcom_escape($member['first_name']) . " /" . gedcom_escape($member['last_name']) . "/\n";
        if ($member['middle_name']) {
            echo "2 GIVN " . gedcom_escape($member['first_name'] . " " . $member['middle_name']) . "\n";
        } else {
            echo "2 GIVN " . gedcom_escape($member['first_name']) . "\n";
        }
        echo "2 SURN " . gedcom_escape($member['last_name']) . "\n";
        
        if ($member['gender']) {
            echo "1 SEX " . substr($member['gender'], 0, 1) . "\n";
        }
        
        if ($member['date_of_birth']) {
            echo "1 BIRT\n";
            echo "2 DATE " . format_gedcom_date($member['date_of_birth']) . "\n";
            if ($member['birth_place']) {
                echo "2 PLAC " . gedcom_escape($member['birth_place']) . "\n";
            }
        }
        
        if ($member['date_of_death']) {
            echo "1 DEAT\n";
            echo "2 DATE " . format_gedcom_date($member['date_of_death']) . "\n";
            if ($member['death_place']) {
                echo "2 PLAC " . gedcom_escape($member['death_place']) . "\n";
            }
        }
        
        if ($member['notes']) {
            echo "1 NOTE " . gedcom_escape($member['notes']) . "\n";
        }
    }

    // Export relationships
    $family_count = 0;
    foreach ($relationships as $rel) {
        if ($rel['relationship_type'] === 'parent-child' || $rel['relationship_type'] === 'spouse') {
            $family_count++;
            echo "\n0 @F" . $family_count . "@ FAM\n";
            
            if ($rel['relationship_type'] === 'spouse') {
                echo "1 HUSB @I" . $rel['person1_id'] . "@\n";
                echo "1 WIFE @I" . $rel['person2_id'] . "@\n";
                if ($rel['marriage_date']) {
                    echo "1 MARR\n";
                    echo "2 DATE " . format_gedcom_date($rel['marriage_date']) . "\n";
                }
                if ($rel['divorce_date']) {
                    echo "1 DIV\n";
                    echo "2 DATE " . format_gedcom_date($rel['divorce_date']) . "\n";
                }
            } else {
                echo "1 HUSB @I" . $rel['person1_id'] . "@\n";
                echo "1 CHIL @I" . $rel['person2_id'] . "@\n";
            }
        }
    }

    echo "\n0 TRLR\n";

} elseif ($format === 'pdf') {
    // Generate PDF using TCPDF
    require_once('includes/tcpdf/tcpdf.php');

    class MYPDF extends TCPDF {
        public function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, 'Family Tree: ' . $GLOBALS['tree']['name'], 0, true, 'C');
        }

        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . 
                       $this->getAliasNbPages(), 0, false, 'C');
        }
    }

    // Create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Family Tree Maker');
    $pdf->SetAuthor($_SESSION['full_name']);
    $pdf->SetTitle($tree['name'] . ' - Family Tree');

    // Set default header data
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 11);

    // Add tree description if available
    if ($tree['description']) {
        $pdf->Write(0, $tree['description'], '', 0, 'L', true, 0, false, false, 0);
        $pdf->Ln(10);
    }

    // Add members list
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Family Members', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);

    foreach ($members as $member) {
        $pdf->Write(0, $member['first_name'] . ' ' . $member['last_name'], '', 0, 'L', true);
        
        $details = [];
        if ($member['date_of_birth']) {
            $details[] = 'Born: ' . date('F j, Y', strtotime($member['date_of_birth']));
        }
        if ($member['date_of_death']) {
            $details[] = 'Died: ' . date('F j, Y', strtotime($member['date_of_death']));
        }
        
        if (!empty($details)) {
            $pdf->SetFont('helvetica', 'I', 10);
            $pdf->Write(0, implode(' | ', $details), '', 0, 'L', true);
            $pdf->SetFont('helvetica', '', 11);
        }
        
        if ($member['notes']) {
            $pdf->Write(0, $member['notes'], '', 0, 'L', true);
        }
        
        $pdf->Ln(5);
    }

    // Add relationships section
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Family Relationships', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 11);

    foreach ($relationships as $rel) {
        $relationship_text = '';
        if ($rel['relationship_type'] === 'spouse') {
            $relationship_text = $rel['p1_first_name'] . ' ' . $rel['p1_last_name'] . 
                               ' is married to ' . 
                               $rel['p2_first_name'] . ' ' . $rel['p2_last_name'];
            
            if ($rel['marriage_date']) {
                $relationship_text .= ' (Married: ' . date('F j, Y', strtotime($rel['marriage_date'])) . ')';
            }
        } else if ($rel['relationship_type'] === 'parent-child') {
            $relationship_text = $rel['p1_first_name'] . ' ' . $rel['p1_last_name'] . 
                               ' is parent of ' . 
                               $rel['p2_first_name'] . ' ' . $rel['p2_last_name'];
        }
        
        $pdf->Write(0, $relationship_text, '', 0, 'L', true);
        $pdf->Ln(5);
    }

    // Output PDF
    $pdf->Output(sanitize_input($tree['name']) . '.pdf', 'D');
}

// Helper functions for GEDCOM export
function gedcom_escape($text) {
    // Replace @ with @@, newlines with proper GEDCOM continuation
    return str_replace(['@', "\n"], ['@@', "\n2 CONT "], $text);
}

function format_gedcom_date($date) {
    return strtoupper(date('d M Y', strtotime($date)));
}
