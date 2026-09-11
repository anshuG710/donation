<?php

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
|
| Cross-Site Request Forgery (CSRF) token generation and validation.
| Used to protect forms from unauthorized submissions.
|
*/

/**
 * Initialize CSRF token in session
 */
function csrf_init() {
    if (!isset($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
}

/**
 * Get the current CSRF token
 */
function csrf_token() {
    csrf_init();
    return $_SESSION["csrf_token"];
}

/**
 * Output CSRF token as a hidden form field
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST request
 */
function csrf_verify() {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        return true;
    }
    
    $token_from_request = $_POST["csrf_token"] ?? "";
    $token_from_session = $_SESSION["csrf_token"] ?? "";
    
    // Constant-time comparison to prevent timing attacks
    return hash_equals($token_from_session, $token_from_request);
}

?>