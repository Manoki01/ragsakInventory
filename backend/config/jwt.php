<?php

function loadBackendEnv($filePath) {
    if (!is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            continue;
        }

        $parts = explode('=', $trimmedLine, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function getJwtSecret() {
    static $jwtSecret = null;

    if ($jwtSecret !== null) {
        return $jwtSecret;
    }

    loadBackendEnv(__DIR__ . '/../.env');

    $jwtSecret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? getenv('JWT_SECRET');

    if (!$jwtSecret) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "JWT_SECRET is not configured"
        ]);
        exit;
    }

    return $jwtSecret;
}

function getRequiredEnv($name) {
    static $envLoaded = false;

    if (!$envLoaded) {
        loadBackendEnv(__DIR__ . '/../.env');
        $envLoaded = true;
    }

    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

    if ($value === false || $value === null || $value === '') {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => $name . " is not configured"
        ]);
        exit;
    }

    return $value;
}
