<?php

// Router for PHP's built-in development server. Use with a loopback bind.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}

$root = realpath(__DIR__ . '/../apps');
$url = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$parts = explode('/', trim($url, '/'));
foreach ($parts as $part) {
    if (str_starts_with($part, '.') || str_contains($part, '\\') || str_contains($part, "\0")) {
        http_response_code(404);
        exit;
    }
}

$app = $parts[0];
if (!in_array($app, ['fuel', 'search', 'lexicon', 'edfed', 'patients', 'patients-legacy',
                    'media', 'twitter', 'diary', 'learning', 'learning-names'], true)) {
    http_response_code(404);
    exit;
}

$file = realpath($root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}
if (array_intersect($parts, ['files', 'storage'])) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!is_file($file) || !in_array($extension, ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'mp4', 'webm', 'txt', 'doc', 'docx'], true)) {
        http_response_code(404);
        exit;
    }
    header('X-Content-Type-Options: nosniff');
}

// Several apps use the same session keys for unrelated user records.
session_name('LegacyProject' . str_replace('-', '', $app));
session_set_cookie_params(['path' => '/' . $app . '/', 'httponly' => true, 'samesite' => 'Lax']);

return false;
