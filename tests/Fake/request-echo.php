<?php

declare(strict_types=1);

// Echoes the X-Story and Authorization headers, the form fields and the uploaded files of a request.
header('Content-Type: application/json');
echo json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'story' => $_SERVER['HTTP_X_STORY'] ?? null,
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'post' => $_POST,
    'files' => array_map(static fn (array $file): array => ['name' => $file['name'], 'type' => $file['type'], 'size' => $file['size']], $_FILES),
]);
