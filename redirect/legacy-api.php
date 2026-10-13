<?php

/**
 * Legacy redirect stub — public API ("api.php" route).
 *
 * Place this file on the OLD host (where the application used to live, e.g.
 * https://pr.lapas.info/jar/) so that old API endpoints keep working after the
 * application has been moved to its own sub-domain.
 *
 * Deploy it as `api.php` in the old document root. It is named `legacy-api.php`
 * here only so that it does not clash with the real `api.php` in this repository.
 *
 * It redirects to the new location, preserving every path segment and all query
 * parameters (e.g. /jar/api.php?title=uab&limit=20 -> https://jar.lapas.info/api.php?title=uab&limit=20).
 *
 * NOTE: clients calling the API must follow redirects. If you would rather the
 * old endpoint proxy the request instead of redirecting it, tell me and this file
 * can be rewritten to forward the response body.
 */
declare(strict_types=1);

// --- Configuration ---------------------------------------------------------
// Full base URL of the application's new location (no trailing slash).
const NEW_BASE_URL = 'https://jar.lapas.info';
// The sub-folder the OLD site was served from on its host. Use '' if it was at the root.
const OLD_SUBDIR = '/jar';
// HTTP status for the redirect: 301 = permanent (cached), 302 = temporary.
const REDIRECT_STATUS = 301;
// ---------------------------------------------------------------------------

// Nothing to do from the command line.
if (PHP_SAPI === 'cli') {
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Path without the query string.
$path = strtok($requestUri, '?');
if ($path === false) {
    $path = '/';
}

// Query string, preserved byte-for-byte.
$query = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';

// Drop the old sub-folder prefix, keeping every remaining segment.
if (OLD_SUBDIR !== '') {
    if ($path === OLD_SUBDIR) {
        $path = '';
    } elseif (strpos($path, OLD_SUBDIR . '/') === 0) {
        $path = substr($path, strlen(OLD_SUBDIR));
    }
}

// Map the legacy stub file names back to the canonical route names, so the
// redirect also works if this file is deployed without being renamed.
$legacyNames = ['legacy-index.php' => 'index.php', 'legacy-api.php' => 'api.php'];
$file = basename($path);
if ($file !== '' && isset($legacyNames[$file])) {
    // Keep the directory part intact and swap only the file name.
    $path = substr($path, 0, strlen($path) - strlen($file)) . $legacyNames[$file];
}

// A directory request maps to the new landing page.
if ($path === '') {
    $path = '/';
}

$target = rtrim(NEW_BASE_URL, '/') . $path . $query;

header('Location: ' . $target, true, REDIRECT_STATUS);
exit;
