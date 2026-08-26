<?php
/**
 * Sudarshan Yuvak Mandal - Native Environment Loader
 * Secure, zero-dependency environment configuration reader (.env parser)
 */

declare(strict_types=1);

class Env {
    private static bool $loaded = false;
    private static array $variables = [];

    public static function load(string $envFilePath): void {
        if (self::$loaded) {
            return;
        }

        if (file_exists($envFilePath) && is_readable($envFilePath)) {
            $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }

                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Strip enclosing quotes if present
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }

                    self::$variables[$key] = $value;
                    $_ENV[$key] = $value;
                    putenv("{$key}={$value}");
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed {
        if (!self::$loaded) {
            self::load(__DIR__ . '/../.env');
        }

        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $envVal = getenv($key);
        if ($envVal !== false) {
            return $envVal;
        }

        return $_ENV[$key] ?? $default;
    }
}

// Auto-load on include
Env::load(__DIR__ . '/../.env');
