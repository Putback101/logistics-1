<?php
// Computes project root URL even if you are inside /views or /auth
function app_base_url(): string {
    $path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); // e.g. /logistics-1/views
    if (str_ends_with($path, '/views')) $path = substr($path, 0, -5);
    if (str_ends_with($path, '/auth'))  $path = substr($path, 0, -5);
    return rtrim($path, '/');
}
