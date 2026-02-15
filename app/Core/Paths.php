<?php

declare(strict_types=1);

namespace App\Core;

final class Paths
{
    public static function basePath(string $suffix = ''): string
    {
        $path = dirname(__DIR__, 2);
        if ($suffix === '') {
            return $path;
        }

        return $path . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $suffix), DIRECTORY_SEPARATOR);
    }

    public static function baseUrl(): string
    {
        // 1) Prefer filesystem-based detection (robust on localhost/subfolders).
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $appRoot = realpath(self::basePath()) ?: '';

        if ($docRoot !== '' && $appRoot !== '') {
            $docNorm = str_replace('\\', '/', strtolower(rtrim($docRoot, '\\/')));
            $appNorm = str_replace('\\', '/', strtolower(rtrim($appRoot, '\\/')));

            if (str_starts_with($appNorm, $docNorm)) {
                $relative = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
                $relative = trim($relative, '/');
                if ($relative !== '') {
                    return '/' . $relative;
                }
                return '';
            }
        }

        // 2) Fallback to URL-segment based detection.
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $dir = str_replace('\\', '/', dirname($scriptName));
        $segments = array_values(array_filter(
            explode('/', trim($dir, '/')),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.'
        ));

        $cutIndex = null;
        foreach ($segments as $i => $segment) {
            if (in_array($segment, ['views', 'auth', 'controllers', 'public'], true)) {
                $cutIndex = $i;
                break;
            }
        }

        if ($cutIndex !== null) {
            $segments = array_slice($segments, 0, $cutIndex);
        }

        $base = '/' . implode('/', $segments);
        if ($base !== '/') {
            return rtrim($base, '/');
        }

        // 3) Last resort for localhost.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (preg_match('/^(localhost|127\.0\.0\.1)(:\\d+)?$/', $host)) {
            $project = basename(self::basePath());
            if ($project !== '') {
                return '/' . trim($project, '/');
            }
        }

        return '';
    }

    public static function redirect(string $path): void
    {
        $base = self::baseUrl();
        $target = str_starts_with($path, '/') ? $path : '/' . $path;
        header('Location: ' . $base . $target);
        exit;
    }
}
