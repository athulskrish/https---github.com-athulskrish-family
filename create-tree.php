<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
redirect_if_not_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['flash_message'] = "Invalid request.";
        $_SESSION['flash_type'] = "danger";
        header("Location: dashboard.php");
        exit();
    }

    // Sanitize input
    $name = sanitize_input($_POST['name']);
    $description = sanitize_input($_POST['description']);
    $privacy_level = sanitize_input($_POST['privacy_level']);

    // Validate input
    if (empty($name)) {
        $_SESSION['flash_message'] = "Tree name is required.";
        $_SESSION['flash_type'] = "danger";
        header("Location: dashboard.php");
        exit();
    }

    // Validate privacy level
    $allowed_privacy_levels = ['private', 'shared', 'public'];
    if (!in_array($privacy_level, $allowed_privacy_levels)) {
        $privacy_level = 'private';
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Create new family tree
        $stmt = $conn->prepare("
            INSERT INTO family_trees (name, description, owner_id, privacy_level)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $_SESSION['user_id'], $privacy_level]);
        
        $tree_id = $conn->lastInsertId();

        $_SESSION['flash_message'] = "Family tree created successfully!";
        $_SESSION['flash_type'] = "success";
        
        // Redirect to the new family tree page
        header("Location: family-tree.php?id=" . $tree_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Error creating family tree. Please try again.";
        $_SESSION['flash_type'] = "danger";
        header("Location: dashboard.php");
        exit();
    }
} else {
    // If accessed directly without POST, redirect to dashboard
    header("Location: dashboard.php");
    exit();
}
