<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Set logout message
session_start();
$_SESSION['flash_message'] = "You have been successfully logged out.";
$_SESSION['flash_type'] = "success";

// Redirect to login page
redirect_to('login.php');
exit();
