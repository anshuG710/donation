<?php

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
|
| Utility functions used throughout the DONATE+ Nepal application.
|
*/

/**
 * Check if a column exists in a table
 * Used to support progressive database migrations
 */
function column_exists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

/**
 * Check if a table exists in the database
 * Used for optional features that may not be migrated yet
 */
function table_exists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

/**
 * Get category name by ID
 * Returns the category name or empty string if not found
 */
function category_name_by_id($conn, $category_id) {
    if ($category_id <= 0) {
        return "";
    }
    
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return "";
    }
    
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? $row["name"] : "";
}

/**
 * Format currency for display
 */
function format_currency($amount) {
    return "Rs. " . number_format($amount, 2);
}

/**
 * Format date for display
 */
function format_date($date) {
    if (!$date) {
        return "N/A";
    }
    return date("M d, Y", strtotime($date));
}

/**
 * Format datetime for display
 */
function format_datetime($datetime) {
    if (!$datetime) {
        return "N/A";
    }
    return date("M d, Y h:i A", strtotime($datetime));
}

/**
 * Sanitize string for display (prevent XSS)
 */
function sanitize($str) {
    return htmlspecialchars($str, ENT_QUOTES, "UTF-8");
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"]);
}

/**
 * Check if user has a specific role
 */
function has_role($role) {
    return isset($_SESSION["user_role"]) && $_SESSION["user_role"] === $role;
}

/**
 * Redirect to login if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.html");
        exit();
    }
}

/**
 * Redirect to dashboard if trying to access auth page while logged in
 */
function require_logout() {
    if (is_logged_in()) {
        if (has_role("admin")) {
            header("Location: admin-dashboard.php");
        } elseif (has_role("recipient")) {
            header("Location: recipient-dashboard.php");
        } else {
            header("Location: donor-dashboard.php");
        }
        exit();
    }
}

/**
 * Require admin role
 */
function require_admin() {
    require_login();
    if (!has_role("admin")) {
        header("Location: index.html");
        exit();
    }
}

/**
 * Get time ago string (e.g. "2 hours ago")
 */
function time_ago($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        return intval($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return intval($diff / 3600) . " hours ago";
    } elseif ($diff < 2592000) {
        return intval($diff / 86400) . " days ago";
    } else {
        return intval($diff / 2592000) . " months ago";
    }
}

?>