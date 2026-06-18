<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TaskController;

Route::apiResource('tasks', TaskController::class);



//security stuff
Route::post('/login', [
    AuthController::class,
    'login'
]);
Route::post('/logout', [
    AuthController::class,
    'logout'
])->middleware('auth:sanctum');

Route::post('/register', [
    AuthController::class,
    'register'
]);


Route::get('/ping', fn() => ['pong' => true]);

// Sequential array → serializes to a JSON array: ["alpha","beta","gamma"]
Route::get('/ping-array', fn() => ['alpha', 'beta', 'gamma']);
