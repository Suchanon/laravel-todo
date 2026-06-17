# Phase 4: Security (Auth & Authorization)

> Part of the [Laravel 13 for NestJS Developers](LEARNING_JOURNEY.md) journey. Previous: [Phase 3](phase-3-business-logic-di.md).

## 1. The Conceptual Bridge

| NestJS Concept | Laravel 13 Equivalent | Note |
|---|---|---|
| `AuthGuard('jwt')` / Passport | **Sanctum** + `auth:sanctum` middleware | Token-based API auth |
| Guard (`canActivate`) — "are you logged in?" | **Middleware** (`auth:sanctum`) | **Authentication** |
| `RolesGuard` / CASL — "can you touch THIS?" | **Policy** (`app/Policies/*`) | **Authorization** |
| `@UseGuards(...)` on a controller | `->middleware('auth:sanctum')` / `HasMiddleware` | Route protection |
| `@Req() req.user` | `$request->user()` / `auth()->user()` | Current user |
| `bcrypt`/`argon` hashing | `'password' => 'hashed'` cast (auto) | Already on your `User` |

> **The key mental shift:** NestJS often **fuses** authentication and authorization into one Guard. Laravel deliberately **splits** them:
> - **Middleware** answers *"are you a valid logged-in user?"* (authentication) → `401` if not.
> - **Policy** answers *"are you allowed to act on THIS specific record?"* (authorization) → `403` if not.
>
> This separation is the whole point of the phase. A logged-in user (passes middleware) must still be blocked from editing *someone else's* task (fails the policy).

---

## 2. Make `User` Issue Tokens

Sanctum is already installed (from `install:api` in Phase 1). Add the `HasApiTokens` trait so users can mint tokens — without it, `createToken()` does not exist (a runtime error like the `validated()`-on-base-`Request` one from Phase 2).

```php
// app/Models/User.php
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]   // your existing attribute-based config
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;   // ← add HasApiTokens
}
```

---

## 3. Auth Endpoints — Register / Login / Logout

A plain controller that mints and revokes tokens. The token is what the client sends on every future request (like a JWT in an `Authorization: Bearer` header).

```bash
php artisan make:controller AuthController
```

```php
public function login(Request $request): JsonResponse
{
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    $user = User::where('email', $credentials['email'])->first();

    if (! $user || ! Hash::check($credentials['password'], $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]); // → 422
    }

    return response()->json([
        'token' => $user->createToken('api')->plainTextToken,
    ]);
}

public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete(); // revoke the token used now

    return response()->json(['message' => 'Logged out']);
}
```

```php
// routes/api.php
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
```

> `plainTextToken` is shown **once** at creation — the DB only stores a hash. Same as a password: you can't read it back, only issue a new one.

---

## 4. Protect Routes — the Middleware (Authentication)

Wrap the task routes so only authenticated requests reach them. This is your `@UseGuards(AuthGuard)`:

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tasks', TaskController::class);
});
```

Now any request without a valid `Authorization: Bearer <token>` header gets `401 Unauthenticated` — before your controller even runs. Inside the controller, `$request->user()` is the authenticated `User`.

**Modern controller-level alternative (Laravel 11+ `HasMiddleware`):** instead of decorators, a controller can declare its own middleware via a static method (this replaced the old `__construct()->middleware()` calls):

```php
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TaskController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth:sanctum'];
    }
}
```

Either approach works — the route-group form is the simplest for one resource.

---

## 5. Scope Data to the Owner (the part curl won't catch)

Authentication alone is **not enough**. Right now `index()` returns `Task::all()` and `show()` returns *any* task by id — so any logged-in user can read everyone's tasks. Two things must change:

**5a. Schema + relationships** — tasks belong to a user.

```php
// new migration: add user_id
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
```

```php
// app/Models/Task.php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

// app/Models/User.php
public function tasks(): HasMany
{
    return $this->hasMany(Task::class);
}
```

> ⚠️ **Keep `user_id` OUT of `$fillable`.** This is the Phase 2 mass-assignment footgun: if `user_id` were fillable, a client could POST `{"user_id": 999}` and create tasks for another user. Set it via the relationship instead (next).

**5b. Create + read through the relationship** — `user_id` is set automatically, never from input:

```php
public function store(StoreTaskRequest $request)
{
    $task = $request->user()->tasks()->create($request->validated()); // user_id auto-set

    return (new TaskResource($task))->response()->setStatusCode(201);
}

public function index(Request $request)
{
    return TaskResource::collection($request->user()->tasks()->latest()->get()); // only MY tasks
}
```

---

## 6. Policies — Authorization on a Single Record

`index`/`store` are now safe (scoped to the user). But `show`/`update`/`destroy` take a `{task}` id via Route Model Binding — a user could still pass *someone else's* task id. A **Policy** is the ownership gate (your `RolesGuard`/CASL equivalent).

```bash
php artisan make:policy TaskPolicy --model=Task
```

```php
// app/Policies/TaskPolicy.php — return true only if the task belongs to the user
public function view(User $user, Task $task): bool   { return $user->id === $task->user_id; }
public function update(User $user, Task $task): bool { return $user->id === $task->user_id; }
public function delete(User $user, Task $task): bool { return $user->id === $task->user_id; }
```

Wire it to the controller in one line — `authorizeResource` maps each resource method to its policy ability automatically (keeps the controller thin, the Phase 3 win):

```php
public function __construct(
    private readonly TaskService $tasks,
) {
    $this->authorizeResource(Task::class, 'task'); // show→view, update→update, destroy→delete
}
```

Now `show`/`update`/`destroy` auto-return `403 Forbidden` if the task isn't yours — before your method body runs.

---

## 🎯 Micro-Assignment (complete before Phase 5)

> **First:** run `php artisan migrate:fresh`. Adding a non-nullable `user_id` to your populated `tasks` table will otherwise fail. Fresh start is cleanest for learning (you'll seed a user below).

1. Add `HasApiTokens` to `User`.
2. Create the `user_id` migration (`foreignId('user_id')->constrained()->cascadeOnDelete()`), add `user()` / `tasks()` relationships. **Do not** add `user_id` to `$fillable`.
3. `make:controller AuthController`; implement `register`, `login`, `logout`; add the 3 routes.
4. Wrap `apiResource('tasks', ...)` in `Route::middleware('auth:sanctum')->group(...)`.
5. Change `store` and `index` to go through `$request->user()->tasks()`.
6. `make:policy TaskPolicy --model=Task`, implement ownership checks, call `authorizeResource` in the controller constructor.

**Verification (must include the negative test):**
- `GET /api/tasks` with **no token** → `401`
- Register/login user A, create a task with A's token → `201`
- `GET /api/tasks` with A's token → returns only A's tasks
- Register/login user B; `GET /api/tasks/{A's task id}` with **B's token** → `403` (not `200`!)

> ⚠️ A single-user happy-path run will pass and still be insecure. The **user-B-gets-403** test is the one that proves the phase actually works.

---

### 📝 [Phase 4] Developer Log & Reference Notes

**Side-by-Side Syntax Reference**

| Concern | NestJS | Laravel 13 |
|---|---|---|
| Token auth strategy | `AuthGuard('jwt')` / Passport | Sanctum + `auth:sanctum` |
| Protect a route | `@UseGuards(AuthGuard)` | `->middleware('auth:sanctum')` |
| Controller-level guard | `@UseGuards()` on class | `implements HasMiddleware` + `middleware()` |
| Current user | `@Req() req.user` | `$request->user()` |
| Issue a token | `jwtService.sign(...)` | `$user->createToken('api')->plainTextToken` |
| Revoke a token | blacklist / short TTL | `$user->currentAccessToken()->delete()` |
| Hash a password | `bcrypt.hash()` | `'password' => 'hashed'` cast (auto on save) |
| Verify a password | `bcrypt.compare()` | `Hash::check($plain, $hashed)` |
| Resource authorization | `RolesGuard` / CASL ability | Policy + `authorizeResource()` / `$this->authorize()` |
| Scope a query to user | manual `where(userId)` | `$request->user()->tasks()` (relationship) |

**Gotchas for TypeScript Devs**
- **Authentication ≠ authorization, and Laravel splits them.** `auth:sanctum` only proves *who* you are (`401`). It does **not** stop you touching another user's record — that's a Policy's job (`403`). Forgetting the policy = a "logged in" app that still leaks every user's data. Your happy-path test won't reveal it; the cross-user test will.
- **`user_id` must never be mass-assignable.** Set ownership via the relationship (`$user->tasks()->create(...)`), and keep `user_id` out of `$fillable`. Otherwise a client sets `user_id` in the JSON body and creates/owns records as anyone — the Phase 2 footgun, now with a security impact.
- **The plaintext token exists exactly once.** `->plainTextToken` is your only chance to read it; the DB stores a hash. There's no "get my token again" — you re-issue. Treat it like a generated password, not a readable field.
- **Route Model Binding fetches the record *before* the policy runs**, so a non-owner still triggers a DB lookup. That's why `view`/`update`/`delete` policy methods receive the already-resolved `$task` — they compare ownership, they don't re-query.

**3-Line Command Summary**
```bash
php artisan make:controller AuthController        # register/login/logout
php artisan make:policy TaskPolicy --model=Task   # ownership authorization
php artisan migrate:fresh                         # reset + apply user_id schema
```

---

> **Next:** Phase 5 — Testing (Jest → Pest/PHPUnit, feature tests, factories, mocking). *(Not written yet.)*
