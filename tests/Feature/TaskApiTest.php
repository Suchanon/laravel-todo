<?php

use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated users cannot list tasks', function () {
    $this->getJson('/api/tasks')->assertUnauthorized(); // 401
});

test('a user only sees their own tasks', function () {
    $user = User::factory()->create();
    Task::factory()->count(2)->for($user)->create(); // 2 of mine
    Task::factory()->count(3)->create(); // 3 of someone else's bc in factory user auto create

    Sanctum::actingAs($user);

    $this->getJson('/api/tasks')
        ->assertOk()
        ->assertJsonCount(2, 'data'); // only MY 2
});

test('a user can create a task', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/tasks', [
        'title' => 'Write tests'
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Write tests');
    $this->assertDatabaseHas('tasks', [
        'title' => 'Write tests',
        'user_id' => $user->id  // ownership was set correctly
    ]);
});

test('creating a task requires a title', function(){
    $user = User::factory()->Create();
    Sanctum::actingAs($user);

    $this->postJson('/api/tasks', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');
});

//NOTE DRY
// beforeEach(function () {
//     $this->user = User::factory()->create();
// });
//group test by using describe('authenticated' / 'unauthenticated')