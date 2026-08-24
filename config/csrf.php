<?php

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
|
| Small helpers used by every admin form that changes data.
| A session must already be started before these are called
| (admin-guard.php starts it).
|
*/


/*
| Return the current CSRF token, creating one if needed.
*/

function csrf_token()
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}


/*
| Output a hidden <input> holding the token. Drop this inside any
| POST form that performs an action.
*/

function csrf_field()
{
    $token = htmlspecialchars(csrf_token());

    echo '<input type="hidden" name="csrf_token" value="' . $token . '">';
}


/*
| Verify the token submitted with a POST request.
| Returns true when valid, false otherwise.
*/

function csrf_verify()
{
    $sent = $_POST["csrf_token"] ?? "";

    $stored = $_SESSION["csrf_token"] ?? "";

    if ($sent === "" || $stored === "") {
        return false;
    }

    return hash_equals($stored, $sent);
}


/*
| Convenience: verify or stop with a flash message + redirect.
| Call at the top of any action handler.
*/

function csrf_require($redirect_to)
{
    if (!csrf_verify()) {

        set_flash("error", "Security check failed. Please try again.");

        header("Location: " . $redirect_to);
        exit();
    }
}
