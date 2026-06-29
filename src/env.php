<?php
/**
 * Simple .env file loader
 * Reads KEY=VALUE pairs and puts them in $_ENV
 */
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        throw new RuntimeException(".env file not found at: $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Split on first = only
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;

        $key   = trim($parts[0]);
        $value = trim($parts[1]);

        // Strip surrounding quotes first
        $len = strlen($value);
        $isQuoted = $len >= 2 && (
            ($value[0] === '"'  && $value[$len-1] === '"') ||
            ($value[0] === "'"  && $value[$len-1] === "'")
        );

        if ($isQuoted) {
            // Quoted values: strip quotes, preserve everything inside literally
            $value = substr($value, 1, -1);
        } else {
            // Unquoted values: strip inline comments (e.g. value # comment)
            if (strpos($value, ' #') !== false) {
                $value = trim(explode(' #', $value, 2)[0]);
            }
        }

        // Set in environment (don't overwrite already-set vars like in CI/CD)
        if (!isset($_ENV[$key]) && !getenv($key)) {
            $_ENV[$key]  = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * Get an env value with optional default
 */
function env(string $key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false) return $default;

    // Cast booleans — PHP 7.4 compatible
    switch (strtolower($value)) {
        case 'true':  return true;
        case 'false': return false;
        case 'null':  return null;
        default:      return $value;
    }
}
