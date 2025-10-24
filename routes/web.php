<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Fallback route for API calls that might be hitting web routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Do not add a manual OPTIONS preflight responder.
// CORS is handled globally by the framework middleware per config/cors.php

// Test route to verify the API is working
Route::get('/test', function () {
    return response()->json(['message' => 'Web routes are working']);
});

// Temporary pass-through for frontend calling root paths instead of /api/*
// This forwards the request to the matching /api/* endpoint without redirecting.
Route::any('/user', function (Request $request) {
    return app()->handle(Request::create('/api/user', $request->method(), $request->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent()));
});

Route::any('/teacher/{path?}', function (Request $request, $path = '') {
    return app()->handle(Request::create('/api/teacher/'.ltrim($path, '/'), $request->method(), $request->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent()));
})->where('path', '.*');

Route::any('/admin/{path?}', function (Request $request, $path = '') {
    return app()->handle(Request::create('/api/admin/'.ltrim($path, '/'), $request->method(), $request->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent()));
})->where('path', '.*');

Route::any('/student/{path?}', function (Request $request, $path = '') {
    return app()->handle(Request::create('/api/student/'.ltrim($path, '/'), $request->method(), $request->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent()));
})->where('path', '.*');

Route::any('/notifications{suffix?}', function (Request $request, $suffix = '') {
    $suffix = $suffix ?? '';
    return app()->handle(Request::create('/api/notifications'.($suffix ?? ''), $request->method(), $request->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent()));
})->where('suffix', '.*');

// Route::get('/', function () {
//     return view('welcome');
// });
