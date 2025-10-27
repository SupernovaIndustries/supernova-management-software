<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

echo "<h1>Debug Info</h1>";
echo "<h2>Session Info</h2>";
echo "<pre>";
echo "Session Driver: " . config('session.driver') . "\n";
echo "Session Path: " . session_save_path() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Storage Path Writable: " . (is_writable(storage_path('framework/sessions')) ? 'Yes' : 'No') . "\n";
echo "</pre>";

echo "<h2>Environment</h2>";
echo "<pre>";
echo "APP_URL: " . env('APP_URL') . "\n";
echo "APP_ENV: " . env('APP_ENV') . "\n";
echo "APP_DEBUG: " . env('APP_DEBUG') . "\n";
echo "</pre>";

echo "<h2>CSRF Token</h2>";
echo "<pre>";
echo "Token: " . csrf_token() . "\n";
echo "</pre>";

echo "<h2>Test Links</h2>";
echo "<a href='/admin'>Go to Admin</a><br>";
echo "<a href='/admin/login'>Go to Admin Login</a><br>";