<?php

const MEDIA_PENDING_DIRECTORY = __DIR__ . '/../storage/tmpfiles/';
const MEDIA_LIBRARY_DIRECTORY = __DIR__ . '/../storage/files/';
const MEDIA_UPLOAD_TYPES = [
    'gif' => ['image/gif'],
    'jpg' => ['image/jpeg', 'image/jpg'],
    'jpeg' => ['image/jpeg', 'image/jpg'],
    'png' => ['image/png'],
    'mp4' => ['video/mp4'],
    'webm' => ['video/webm'],
];

function pending_media_uploads(): array
{
    $pending = [];
    foreach ($_SESSION['files'] ?? [] as $index => $filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (basename($filename) === $filename && isset(MEDIA_UPLOAD_TYPES[$extension])
            && is_file(MEDIA_PENDING_DIRECTORY . $filename)) {
            $pending[$filename] = $_SESSION['filename_original_name'][$index] ?? $filename;
        }
    }
    return $pending;
}
