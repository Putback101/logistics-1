<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!function_exists('procurement_intake_token')) {
    function procurement_intake_token(): string
    {
        $candidates = [
            getenv('PROCUREMENT_INTAKE_TOKEN') ?: '',
            $_ENV['PROCUREMENT_INTAKE_TOKEN'] ?? '',
            $_SERVER['PROCUREMENT_INTAKE_TOKEN'] ?? '',
        ];

        foreach ($candidates as $value) {
            $token = trim((string)$value);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }
}

if (!function_exists('warehousing_intake_token')) {
    function warehousing_intake_token(): string
    {
        $candidates = [
            getenv('WAREHOUSING_INTAKE_TOKEN') ?: '',
            $_ENV['WAREHOUSING_INTAKE_TOKEN'] ?? '',
            $_SERVER['WAREHOUSING_INTAKE_TOKEN'] ?? '',
        ];

        foreach ($candidates as $value) {
            $token = trim((string)$value);
            if ($token !== '') {
                return $token;
            }
        }

        return procurement_intake_token();
    }
}

if (!function_exists('mro_intake_token')) {
    function mro_intake_token(): string
    {
        $candidates = [
            getenv('MRO_INTAKE_TOKEN') ?: '',
            $_ENV['MRO_INTAKE_TOKEN'] ?? '',
            $_SERVER['MRO_INTAKE_TOKEN'] ?? '',
        ];

        foreach ($candidates as $value) {
            $token = trim((string)$value);
            if ($token !== '') {
                return $token;
            }
        }

        return procurement_intake_token();
    }
}

if (!function_exists('asset_intake_token')) {
    function asset_intake_token(): string
    {
        $candidates = [
            getenv('ASSET_INTAKE_TOKEN') ?: '',
            $_ENV['ASSET_INTAKE_TOKEN'] ?? '',
            $_SERVER['ASSET_INTAKE_TOKEN'] ?? '',
        ];

        foreach ($candidates as $value) {
            $token = trim((string)$value);
            if ($token !== '') {
                return $token;
            }
        }

        return procurement_intake_token();
    }
}
