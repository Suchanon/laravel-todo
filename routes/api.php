<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TaskController;

Route::apiResource('tasks', TaskController::class);

Route::get('/ping', fn () => ['pong' => true]);

// Sequential array → serializes to a JSON array: ["alpha","beta","gamma"]
Route::get('/ping-array', fn () => ['alpha', 'beta', 'gamma']);