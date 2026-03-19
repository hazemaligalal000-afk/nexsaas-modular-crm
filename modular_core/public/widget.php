<?php
/**
 * public/widget.php
 *
 * Serves /widget.js with the correct Content-Type and cache headers.
 * This file is the entry point when the web server rewrites /widget.js → /widget.php.
 *
 * Alternatively, configure the web server to serve widget.js directly from
 * public/widget.js (no PHP needed in that case).
 *
 * Requirements: 12.7
 */

declare(strict_types=1);

$widgetFile = __DIR__ . '/widget.js';

if (!file_exists($widgetFile)) {
    http_response_code(404);
    exit;
}

$etag    = '"' . md5_file($widgetFile) . '"';
$lastMod = gmdate('D, d M Y H:i:s', filemtime($widgetFile)) . ' GMT';

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastMod);
header('Access-Control-Allow-Origin: *');

// Conditional GET support
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

readfile($widgetFile);
