<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Autoloader PSR-4 mínimo para proyectos en PHP puro.
 */
final class Autoloader
{
    /**
     * @var array<string, string>
     */
    private static array $prefixes = [];

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $normalizedPrefix = trim($prefix, '\\') . '\\';
        $normalizedBaseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        self::$prefixes[$normalizedPrefix] = $normalizedBaseDir;
    }

    public static function register(): void
    {
        spl_autoload_register([self::class, 'loadClass']);
    }

    private static function loadClass(string $className): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
                continue;
            }

            $relativeClass = substr($className, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            // Intento estándar PSR-4.
            $file = $baseDir . $relativePath;
            if (is_readable($file)) {
                require_once $file;
                return;
            }

            // Fallback para carpetas físicas en minúscula (controllers, services...).
            $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
            if (count($segments) > 1) {
                $segments[0] = strtolower($segments[0]);
                $fallbackFile = $baseDir . implode(DIRECTORY_SEPARATOR, $segments);

                if (is_readable($fallbackFile)) {
                    require_once $fallbackFile;
                    return;
                }
            }
        }
    }
}
