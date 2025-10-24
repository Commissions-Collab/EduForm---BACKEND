<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in production
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    // Standard Laravel installation
    require __DIR__.'/../vendor/autoload.php';
} else {
    // Check if composer is in parent directory (some hosting configurations)
    require __DIR__.'/../../vendor/autoload.php';
}

// Get the application
if (file_exists(__DIR__.'/../bootstrap/app.php')) {
    // Standard Laravel installation
    $app = require_once __DIR__.'/../bootstrap/app.php';
} else {
    // Check if bootstrap is in parent directory (some hosting configurations)
    $app = require_once __DIR__.'/../../bootstrap/app.php';
}

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);