<?php

/**
 * Lightweight and secure .env file loader for stock-bartolo.
 * Reads environment variables from the project root .env file
 * and populates putenv(), $_ENV, and $_SERVER without exposing credentials.
 */

function sb_load_env(?string $path = null): array
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    $envPath = $path ?? dirname(__DIR__) . '/.env';
    $loaded = [];

    if (!file_exists($envPath) || !is_readable($envPath)) {
        return $loaded;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $loaded;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Split key and value on the first '='
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));

        // Strip surrounding quotes if present
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        // Populate runtime environments
        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
        $loaded[$key] = $val;
    }

    return $loaded;
}

function sb_env(string $key, mixed $default = null): mixed
{
    sb_load_env();
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    return $default;
}

// Auto-load on inclusion
sb_load_env();

