<?php
/**
 * Router script for PHP built-in server with Compatibility Layers
 */
$uri = decodeURI($_SERVER['REQUEST_URI']);
$path = parse_url($uri, PHP_URL_PATH);

function decodeURI($uri) {
    return rawurldecode($uri);
}

// Compatibility layer for static assets and uploads
$filePath = null;

if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js|svg|pdf)$/', $path)) {
    if (strpos($path, '/static/uploads/') === 0) {
        // Map /static/uploads/filename -> /uploads/filename
        $filePath = __DIR__ . '/uploads/' . substr($path, 16);
    } elseif (strpos($path, '/static/') === 0) {
        // Map /static/folder/filename -> /public/assets/folder/filename
        $filePath = __DIR__ . '/public/assets/' . substr($path, 8);
    } elseif (strpos($path, '/uploads/') === 0) {
        // Map /uploads/filename -> /uploads/filename
        $filePath = __DIR__ . $path;
    } else {
        // Standard asset path relative to public/
        $filePath = __DIR__ . '/public' . $path;
    }

    if ($filePath && file_exists($filePath)) {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf'
        ];
        if (isset($mimes[$extension])) {
            header("Content-Type: " . $mimes[$extension]);
        }
        readfile($filePath);
        return true;
    }
}

// Fallback to the new public index router gateway
require_once __DIR__ . '/public/index.php';
