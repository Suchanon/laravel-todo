# Phase 5: Testing (Pest / PHPUnit)

> Part of the [Laravel 13 for NestJS Developers](LEARNING_JOURNEY.md) journey. Previous: [Phase 4](phase-4-security.md).

## 1. The Conceptual Bridge

| NestJS / Jest Concept | Laravel 13 (Pest) Equivalent | Note |
|---|---|---|
| Jest + `supertest` | **Pest** (built on PHPUnit) + Laravel test client | One integrated stack |
| `describe()` / `it()` | `test()` / `it()` (this project uses `test()`) | Match existing style |
| `expect(x).toBe(y)` | `expect($x)->toBe($y)` | Almost identical |
| `beforeEach()` | `beforeEach()` / `RefreshDatabase` | DB reset per test |
| `app.get(...)` test request (supertest) | `$this->getJson('/api/...')` | Built into the framework |
| Mock factory / `faker` fixtures | **Model Factories** (`Task::factory()`) | First-class |
| `jest.mock(...)` | `$this->mock(Service::class)` | Container-swap mock |
| Test DB / Testcontainers | in-memory SQLite + `RefreshDatabase` | Zero setup |
| `@nestjs/testing` `Test.createTestingModule` | The real app boots — no separate test module | Full integration by default |

> **The mindset shift:** In NestJS you often build a *testing module* and inject mocks. In Laravel, **feature tests boot the real application** and hit real routes through the real middleware/container — closer to an end-to-end test, but fast (in-memory DB). You only reach for mocks when you want to *isolate* a unit. Default to feature tests; they catch the most.

---

## 2. Two Kinds of Test

| Type | Dir | Boots the app? | Use for |
|---|---|---|---|
| **Feature** | `tests/Feature` | ✅ Yes (HTTP, DB, middleware) | API endpoints, auth, policies — **most of your tests** |
| **Unit** | `tests/Unit` | ❌ No (plain PHP) | A single class in isolation (e.g. `TaskService` logic) |

```bash
php artisan make:test TaskApiTest          # → tests/Feature/TaskApiTest.php
php artisan make:test --unit TaskServiceTest  # → tests/Unit/TaskServiceTest.php
```

> ⚠️ Don't prefix `Feature/` in the name — `make:test TaskApiTest` already lands in `tests/Feature`.

---

## 3. Setup You Must Do First

**3a. Enable `RefreshDatabase`** — in this project it's commented out in `tests/Pest.php`. Uncomment it so every test runs on a clean, migrated database and rolls back after:

```php
// tests/Pest.php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)   // ← uncomment this
    ->in('Feature');
```

**3b. Fill the empty `TaskFactory`** — tests generate data through factories (your `faker` fixtures). Note the `user_id` is created via a related factory, so a `Task` always has a valid owner:

```php
// database/factories/TaskFactory.php
public function definition(): array
{
    return [
        'user_id' => User::factory(),               // auto-creates an owner
        'title' => fake()->sentence(3),
        'description' => fake()->optional()->paragraph(),
        'is_completed' => false,
        'due_at' => fake()->optional()->dateTimeBetween('now', '+1 month'),
    ];
}
```

> Use a **state** for variations, e.g. `public function completed(): static { return $this->state(['is_completed' => true]); }` → then `Task::factory()->completed()->create()`.

---

## 4. Feature Tests — Hitting the API

This is `supertest`, but native. `Sanctum::actingAs($user)` authenticates the request (skips the real token dance — you're testing the endpoints, not login itself here).

```php
<?php

use App\Models\Task;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('unauthenticated users cannot list tasks', function () {
    $this->getJson('/api/tasks')->assertUnauthorized(); // 401
});

test('a user only sees their own tasks', function () {
    $user = User::factory()->create();
    Task::factory()->count(2)->for($user)->create();   // 2 of mine
    Task::factory()->count(3)->create();               // 3 of someone else's

    Sanctum::actingAs($user);

    $this->getJson('/api/tasks')
        ->assertOk()
        ->assertJsonCount(2, 'data');                  // only MY 2
});

test('a user can create a task', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/tasks', ['title' => 'Write tests'])
        ->assertCreated()                              // 201
        ->assertJsonPath('data.title', 'Write tests');

    $this->assertDatabaseHas('tasks', [
        'title' => 'Write tests',
        'user_id' => $user->id,                        // ownership was set correctly
    ]);
});

test('creating a task requires a title', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/tasks', [])
        ->assertUnprocessable()                        // 422
        ->assertJsonValidationErrors('title');
});
```

> Prefer semantic assertions: `assertOk()`, `assertCreated()`, `assertUnauthorized()`, `assertForbidden()`, `assertUnprocessable()` over `assertStatus(200)`.

---

## 5. The Capstone — Automate the Cross-User `403`

In Phase 4 you proved this *by hand* with curl. A test locks it in forever, so a future change can never silently reopen the hole:

```php
test('a user cannot view another users task', function () {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $intruder = User::factory()->create();
    Sanctum::actingAs($intruder);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden(); // 403
});

test('a user cannot delete another users task', function () {
    $task = Task::factory()->create();                 // owned by a fresh user
    Sanctum::actingAs(User::factory()->create());      // a different user

    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden(); // 403
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);         // still there
});
```

This is the most valuable test in the suite — it encodes the security guarantee as an executable check.

---

## 6. Unit Test — Isolating `TaskService`

A unit test for pure logic, no HTTP. Note: because `TaskService` touches the DB, it still needs `RefreshDatabase` (add the trait via `uses()` since Unit isn't covered by `Pest.php`):

```php
<?php

use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('toggleComplete flips the completed flag', function () {
    $task = Task::factory()->create(['is_completed' => false]);

    $result = app(TaskService::class)->toggleComplete($task);

    expect($result->is_completed)->toBeTrue();
    expect($task->fresh()->is_completed)->toBeTrue();
});
```

> **Bridge to Phase 3:** if `TaskService` depended on an *interface* (the stretch goal), this is where you'd `$this->mock(TaskNotifier::class)` to swap in a fake — the payoff of interface binding finally shows up in tests, exactly like `jest.mock()`.

---

## 7. Architecture Tests (Pest bonus — no Jest equivalent)

Enforce conventions as tests:

```php
arch('controllers are suffixed and thin')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('no debug statements leak')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();
```

---

## 🎯 Micro-Assignment (final phase)

1. Uncomment `->use(RefreshDatabase::class)` in `tests/Pest.php`.
2. Fill in `TaskFactory::definition()` (with `user_id => User::factory()`).
3. `php artisan make:test TaskApiTest` and write feature tests for: unauthenticated `401`, list-only-own, create `201` + `assertDatabaseHas`, validation `422`.
4. Add the **two cross-user `403` tests** (view + delete) — the capstone.
5. `php artisan make:test --unit TaskServiceTest` and test `toggleComplete` and `delete`.
6. Run `php artisan test --compact` and get everything green.

**Stretch:** Add an `arch()` test, and use a **dataset** to test multiple invalid payloads for `store` in one test.

---

### 📝 [Phase 5] Developer Log & Reference Notes

**Side-by-Side Syntax Reference**

| Concern | NestJS / Jest | Laravel 13 (Pest) |
|---|---|---|
| Define a test | `it('...', () => {})` | `test('...', function () {})` |
| Assertion | `expect(x).toBe(y)` | `expect($x)->toBe($y)` |
| HTTP request | `request(app).get(...)` (supertest) | `$this->getJson(...)` |
| Auth a request | mock guard / inject user | `Sanctum::actingAs($user)` |
| Status assert | `.expect(403)` | `->assertForbidden()` |
| JSON shape | `expect(res.body.x)...` | `->assertJsonPath('data.x', ...)` |
| DB assert | query + expect | `assertDatabaseHas('tasks', [...])` |
| Fixtures | manual / faker | `Task::factory()->create()` |
| Relationship fixture | nested create | `Task::factory()->for($user)` |
| Mock a dependency | `jest.mock()` | `$this->mock(X::class)` |
| Reset DB | beforeEach truncate | `RefreshDatabase` trait |
| Run one test | `jest -t name` | `php artisan test --filter=name` |

**Gotchas for TypeScript Devs**
- **`RefreshDatabase` migrates a fresh DB per test and rolls back.** It does NOT use your dev data — tests run against an empty (usually in-memory SQLite) database. If a test expects a record, *create it in the test* with a factory. Forgetting this = "why is my table empty?" confusion.
- **Tests are synchronous — no `async/await`, no `done()` callbacks.** Every assertion runs top-to-bottom and blocks. There's no event loop to await; `$this->getJson()` returns the finished response immediately. Drop all Jest async ceremony.
- **A factory with a relationship creates the parent too.** `Task::factory()->create()` silently creates a `User` (because of `'user_id' => User::factory()`). If a test asserts "only 2 users exist," remember the factories may have made more. Use `->for($existingUser)` to attach to a specific one instead of spawning a new owner.
- **Feature tests boot the *real* app**, including middleware and policies. That's a feature (high confidence) but means a failing policy shows as `403` in the test, not a mock assertion — debug it like the real request it is.

**3-Line Command Summary**
```bash
php artisan make:test TaskApiTest                 # feature test (tests/Feature)
php artisan make:test --unit TaskServiceTest      # unit test (tests/Unit)
php artisan test --compact                        # run all (add --filter=name for one)
```

---

> 🎓 **Journey complete.** You've mapped the full NestJS → Laravel 13 stack: lifecycle & routing, Eloquent & validation, the service container, Sanctum security, and Pest testing. The To-Do API is authenticated, owner-scoped, and test-covered.
