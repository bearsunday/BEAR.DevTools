<?php

declare(strict_types=1);

// Echoes the Expect request header so a test can see whether the client sent "Expect: 100-continue".
header('Content-Type: application/json');
echo json_encode(['expect' => $_SERVER['HTTP_EXPECT'] ?? null, 'length' => strlen((string) file_get_contents('php://input'))]);
