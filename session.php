<?php
declare(strict_types=1);

// Buffer all output for the request. Several pages (e.g. admin_dashboard.php)
// print HTML before including files that later need to call header() via
// redirect() -- without buffering, that triggers a "headers already sent"
// warning and the redirect silently fails.
//
// This always pushes a fresh, unlimited buffer, even if php.ini's own
// output_buffering setting already started one. That's important: on many
// default XAMPP installs, output_buffering is set to a chunk size (e.g.
// 4096) rather than "On" or "Off" -- PHP then auto-flushes (and sends
// headers) as soon as that many bytes accumulate, which happens partway
// through admin_dashboard.php's HTML. Stacking our own buffer with no
// chunk size on top means nothing gets sent to the browser until the
// whole request finishes, so header()/redirect() always works.
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // uncomment once served over HTTPS
    ]);
    session_start();
}

function regenerate_session(): void
{
    session_regenerate_id(true);
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}