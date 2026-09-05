<?php

/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
|
| One-time success / error banners shown after an action redirects.
| Stored in the session, cleared as soon as they are rendered.
|
*/


/*
| Queue a message. $type is "success" or "error".
*/

function set_flash($type, $message)
{
    $_SESSION["flash"][] = [
        "type"    => $type,
        "message" => $message,
    ];
}


/*
| Render (and clear) any queued messages. Called once inside the
| admin header, just under the page title.
*/

function render_flash()
{
    if (empty($_SESSION["flash"])) {
        return;
    }

    foreach ($_SESSION["flash"] as $flash) {

        $type = ($flash["type"] === "success") ? "success" : "error";

        $message = htmlspecialchars($flash["message"] ?? "");

        echo '<div class="flash flash-' . $type . '" role="status">'
            . '<span>' . $message . '</span>'
            . '<button type="button" class="flash-close" aria-label="Dismiss"'
            . ' onclick="this.parentNode.remove()">&times;</button>'
            . '</div>';
    }

    unset($_SESSION["flash"]);

    // Let the banner be dismissed and also fade itself out after a few seconds.
    echo '<script>'
        . '(function(){'
        . 'document.querySelectorAll(".flash").forEach(function(el){'
        . 'setTimeout(function(){'
        . 'el.style.transition="opacity .4s";'
        . 'el.style.opacity="0";'
        . 'setTimeout(function(){ el.remove(); },400);'
        . '},5000);'
        . '});'
        . '})();'
        . '</script>';
}
