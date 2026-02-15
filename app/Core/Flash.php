<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        Session::start();
        $_SESSION['flash'][$type] = $message;
    }

    public static function get(string $type): ?string
    {
        Session::start();

        if (!empty($_SESSION['flash'][$type])) {
            $msg = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $msg;
        }

        return null;
    }
}