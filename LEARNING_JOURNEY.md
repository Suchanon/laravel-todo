# Laravel 13 for NestJS Developers — Learning Journey

> A conceptual-bridging guide for backend devs transitioning from NestJS (TypeScript) to Laravel 13, built around a To-Do API.
>
> **Core mental model:** NestJS is *explicit & declarative* (decorators everywhere). Laravel is *convention-driven & fluent*. That contrast explains most of the "where is the equivalent of X?" friction.

---

# Phase 1: Lifecycle & Routing

## 1. The Conceptual Bridge

| NestJS Concept | Laravel 13 Equivalent | Mental Model |
|---|---|---|
| `main.ts` (`bootstrap()`) | `public/index.php` → `bootstrap/app.php` | The ignition |
| `AppModule` (root module) | `bootstrap/app.php` (`Application::configure()`) | The composition root |
| `@Module({ imports, providers })` | **Service Providers** (`bootstrap/providers.php`) | Wiring/registration |
| `@Controller('tasks')` | `routes/api.php` + a Controller class | Routing is *external* to the class |
| `NestFactory` / IoC container | The **Service Container** (`Application`) | Same idea, auto-resolved |

The single biggest unlearning: **In NestJS, the Controller *declares* its own routes via decorators. In Laravel, routes live in dedicated route files and *point at* controller methods.** The controller is "dumb" about its own URL. There is no `@Module` because Laravel discovers and wires everything through Service Providers + convention instead of an explicit import graph.

---

## 2. How Laravel Bootstraps (the Lifecycle)

```
public/index.php          ← entry point (like main.ts)
   │  loads autoloader + bootstrap/app.php
   ▼
bootstrap/app.php          ← the "AppModule" / composition root
   │  Application::configure()->withRouting()->withMiddleware()->withProviders()
   ▼
bootstrap/providers.php     ← your registered Service Providers
   │  each provider's register() then boot() runs
   ▼
Kernel handles Request → Middleware pipeline → Router → Controller
   ▼
Response travels back out through the middleware pipeline
```

> ⚠️ **Reality check:** Since Laravel 11, the framework adopted a "slim skeleton." The old `app/Http/Kernel.php` and `app/Console/Kernel.php` files **no longer exist**. Everything that used to live there (global middleware, route registration, scheduling) is now configured fluently in **`bootstrap/app.php`**. If a 2023 tutorial tells you to edit `Http/Kernel.php`, it's outdated.

```php
// bootstrap/app.php — your real "AppModule"
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // global/group middleware registration (Phase 4 territory)
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // global exception handling (your "global ExceptionFilter")
    })->create();
```

`->withMiddleware()` and `->withExceptions()` are your global **Interceptors** and **Exception Filters** from NestJS, registered centrally instead of via `app.useGlobalFilters()`.

---

## 3. Routing Without `@Module` / `@Controller` Decorators

NestJS:

```typescript
@Controller('tasks')
export class TaskController {
  @Get(':id')
  findOne(@Param('id') id: string) { ... }
}
```

Laravel — routing lives in a route file and references the controller method. Since the slim skeleton ships without API routes by default, enable them once:

```bash
php artisan install:api
```

This creates `routes/api.php` (auto-prefixed with `/api`) and installs Sanctum (Phase 4). Then:

```php
// routes/api.php
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// Explicit, one route at a time:
Route::get('/tasks/{task}', [TaskController::class, 'show']);

// Or — the idiomatic shortcut — generate all 5 RESTful API routes at once:
Route::apiResource('tasks', TaskController::class);
```

`apiResource` is the closest thing to NestJS's decorator-driven REST mapping — it generates `index`, `store`, `show`, `update`, `destroy` by convention. Run `php artisan route:list --path=api` to *see* them.

> **On "PHP native attributes":** Core Laravel does **NOT** use PHP attributes for route definitions. Routes always live in route files. Community packages (e.g. `spatie/laravel-route-attributes`) allow `#[Get('/tasks')]`, but that is *non-idiomatic*. Learn the route-file way first — the whole ecosystem assumes it. Where Laravel *does* embrace modern attributes/interfaces is **controller middleware** via the `HasMiddleware` interface (Phase 4).

---

## 4. A Basic `TaskController`

Generate with Artisan (don't hand-create — stubs enforce conventions, like `nest g controller`):

```bash
php artisan make:controller TaskController --api
```

`--api` scaffolds resource methods *without* `create`/`edit` (those return HTML forms — irrelevant for an API).

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * GET /api/tasks — list all tasks.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ['Learn routing', 'Build Task API'],
        ]);
    }

    /**
     * GET /api/tasks/{task} — show one task.
     * Note: {task} from the route is injected by name, auto-resolved by the container.
     */
    public function show(string $task): JsonResponse
    {
        return response()->json(['data' => "Task #{$task}"]);
    }

    /**
     * POST /api/tasks — create a task.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json(['received' => $request->all()], 201);
    }
}
```

Two things to notice vs NestJS:
- **No `@Param`, `@Body`, `@Req` decorators.** Laravel injects route parameters by *matching the method argument name* to the route placeholder (`{task}` → `$task`), and injects `Request` by *type-hint* via the container.
- **`extends Controller`** gives nothing magical — the route file is what registers the controller.

---

## 🎯 Micro-Assignment (complete before Phase 2)

1. Run `php artisan install:api` to enable the API routes file.
2. Generate `TaskController` with `--api`.
3. Register it with `Route::apiResource('tasks', TaskController::class);` in `routes/api.php`.
4. Implement `index()` and `show()` to return hardcoded JSON.
5. Run `php artisan route:list --path=api` and confirm all 5 routes appear.
6. Hit `GET /api/tasks` and `GET /api/tasks/5` (via your Herd URL, e.g. `https://todo.test/api/tasks`) and confirm the responses, including that `{task}` correctly injects `5`.

**Stretch:** Add `Route::get('/ping', fn () => ['pong' => true]);` and observe that Laravel auto-serializes a returned array to JSON. Ask yourself *why* (hint: the Router inspects the return type).

---

### 📝 [Phase 1] Developer Log & Reference Notes

**Side-by-Side Syntax Reference**

| Concern | NestJS | Laravel 13 |
|---|---|---|
| Entry point | `main.ts` → `NestFactory.create(AppModule)` | `public/index.php` → `bootstrap/app.php` |
| Composition root | `@Module({ imports, controllers, providers })` | `Application::configure()` in `bootstrap/app.php` |
| Register a feature/provider | add to `@Module.providers` / `imports` | add class to `bootstrap/providers.php` |
| Controller declaration | `@Controller('tasks')` | `class TaskController extends Controller` (no decorator) |
| Route → handler | `@Get(':id') findOne()` | `Route::get('/tasks/{task}', [TaskController::class, 'show'])` |
| All REST routes | manual decorators per method | `Route::apiResource('tasks', TaskController::class)` |
| Route param | `@Param('id') id: string` | name-matched arg: `function show(string $task)` |
| Request body | `@Body() dto` | type-hint: `function store(Request $request)` |
| Global filters/interceptors | `app.useGlobalFilters()` / `useGlobalInterceptors()` | `->withExceptions()` / `->withMiddleware()` in `bootstrap/app.php` |
| Inspect routes | (no built-in) | `php artisan route:list` |

**Gotchas for TypeScript Devs**
- **There is no `@Module` import graph.** Don't hunt for where `TaskController` is "imported/declared." Its existence as a route handler comes *entirely* from the route file referencing it. Forgetting the route = a 404, not a compile error.
- **Route param injection is name-sensitive; type injection is type-sensitive.** `{task}` only maps to a parameter literally named `$task`. Mismatch and you get `null`/an error, with no decorator to make the binding explicit.
- **No top-level `await` / no async controllers.** PHP request handling is synchronous and blocking per request (one process = one request). Methods return values directly, not `Promise<T>`. Concurrency is process-/queue-based (Phase 3+), not language-level promises.

**3-Line Command Summary**
```bash
php artisan install:api                              # enable routes/api.php + Sanctum
php artisan make:controller TaskController --api     # scaffold resource controller
php artisan route:list --path=api                    # verify registered routes
```

---

> **Next:** Phase 2 — Data Layer & Validation (Prisma/TypeORM + DTOs → Eloquent, Migrations, Form Requests).

---

# Phase 2: Data Layer & Validation

## 1. The Conceptual Bridge

| NestJS / TS Concept | Laravel 13 Equivalent | Note |
|---|---|---|
| Prisma `schema.prisma` / TypeORM `@Entity` | **Migration** (`database/migrations/*`) | The schema source of truth |
| TypeORM Repository (Data Mapper) | **Eloquent Model** (Active Record) | The model *is* the repository |
| `prisma migrate dev` | `php artisan migrate` | Applies schema changes |
| `@Column()` decorators | columns defined in the migration, **not** the model | Model is schema-agnostic |
| DTO + `class-validator` + `ValidationPipe` | **Form Request** (`app/Http/Requests/*`) | Validation + authorization in one class |
| `class-transformer` / serialization interceptor | **Eloquent API Resource** (`app/Http/Resources/*`) | Output shaping (DTO-out) |
| Faker seeding scripts | **Factories** + **Seeders** | First-class, built in |

> **The mindset shift:** TypeORM/Prisma is **Data Mapper** — entity definitions and persistence logic are separate. Eloquent is **Active Record** — the model knows how to save *itself* (`$task->save()`). And critically: **the model does NOT declare columns.** The migration owns the schema; the model owns behavior. There is no `@Column` on the model.

---

## 2. Migrations — Your Schema Source of Truth

Migrations are versioned PHP files describing schema changes — like `prisma migrate`, but you write the schema in a fluent builder instead of a DSL.

```bash
php artisan make:model Task -mf   # -m = migration, -f = factory, all at once
```

```php
// database/migrations/xxxx_create_tasks_table.php
public function up(): void
{
    Schema::create('tasks', function (Blueprint $table) {
        $table->id();                                  // bigint PK, auto-increment
        $table->string('title');
        $table->text('description')->nullable();
        $table->boolean('is_completed')->default(false);
        $table->timestamp('due_at')->nullable();
        $table->timestamps();                          // created_at + updated_at
    });
}
```

```bash
php artisan migrate          # apply (like prisma migrate dev)
php artisan migrate:fresh    # drop all + re-run (dev only — destroys data)
```

> ⚠️ `timestamps()` auto-creates and **auto-maintains** `created_at`/`updated_at`. You never set them manually — Eloquent does it on every save. (No `@CreateDateColumn` needed.)

---

## 3. The Eloquent Model — Active Record

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes. THIS IS SECURITY-CRITICAL — see gotchas.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'is_completed',
        'due_at',
    ];

    /**
     * Attribute casting — the modern Laravel 11+ method form (not the old $casts property).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'due_at' => 'datetime',
        ];
    }
}
```

Notice what's **absent**: no column list, no types, no PK declaration. The model infers all of that from the table at runtime. `casts()` is the one place you re-declare types — and only to convert DB strings into rich PHP types (`bool`, `Carbon` date), exactly like a class-transformer `@Type()`.

Using it (Active Record in action):

```php
$task = Task::create(['title' => 'Learn Eloquent']);  // insert
$task->is_completed = true;
$task->save();                                         // update
$open = Task::where('is_completed', false)->get();     // query
Task::findOrFail($id);                                 // 404 if missing
```

---

## 4. Form Requests = DTO + ValidationPipe in One

In NestJS, validation is a DTO class (`class-validator` decorators) + a `ValidationPipe` that runs it. Laravel fuses both into a **Form Request**: a class with `rules()` (the validation) and `authorize()` (the guard). When you type-hint it in a controller, Laravel **auto-validates before your method runs** — same as a pipe, but with zero wiring.

```bash
php artisan make:request StoreTaskRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    /**
     * Authorization gate — runs BEFORE validation. Return false → 403.
     */
    public function authorize(): bool
    {
        return true; // wire to real auth in Phase 4
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_completed' => ['boolean'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
```

Consume it — the type-hint *is* the trigger:

```php
public function store(StoreTaskRequest $request): JsonResponse
{
    // If we reach this line, input is already validated + authorized.
    $task = Task::create($request->validated()); // only validated keys — safe mass assign

    return response()->json(['data' => $task], 201);
}
```

On failure, Laravel auto-returns `422` with a JSON error bag (because of the `shouldRenderJsonWhen` rule already in your `bootstrap/app.php`). You never write the error response — that's the pipe behavior, built in.

> **`$request->validated()` vs `$request->all()`:** `validated()` returns **only** the keys that passed `rules()`. Always feed *that* into `create()`, never `all()` — it's your defense against mass-assignment injection (see gotchas).

---

## 5. API Resources = Output DTO / Serialization

Returning a raw model dumps every column (including ones you may not want exposed). An **API Resource** is your output DTO — the equivalent of a serialization interceptor + response DTO.

```bash
php artisan make:resource TaskResource
```

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'completed' => $this->is_completed,       // rename on the way out
        'dueAt' => $this->due_at?->toIso8601String(),
        'createdAt' => $this->created_at->toIso8601String(),
    ];
}
```

```php
return new TaskResource($task);            // single
return TaskResource::collection($tasks);   // list
```

---

## 🎯 Micro-Assignment (complete before Phase 3)

1. `php artisan make:model Task -mf` and fill the migration with the columns above.
2. Run `php artisan migrate` and confirm the `tasks` table exists (`php artisan db:show` or the `database-schema` tool).
3. Set `$fillable` + `casts()` on the model.
4. `make:request StoreTaskRequest` with the rules above.
5. Replace your hardcoded `store()` with the Form Request version that does `Task::create($request->validated())`.
6. `POST /api/tasks` with `{"title":"Test"}` → expect `201` + the created row. Then POST `{}` (no title) → expect a `422` with a validation error for `title`.
7. Update `index()` to return `Task::all()` and `show()` to return `Task::findOrFail($task)`.

**Stretch:** Build `TaskResource`, return it from `store()`/`index()`, and confirm `is_completed` comes back as the renamed `completed` boolean.

---

### 📝 [Phase 2] Developer Log & Reference Notes

**Side-by-Side Syntax Reference**

| Concern | NestJS / TS | Laravel 13 |
|---|---|---|
| Define schema | `@Entity()` + `@Column()` / `schema.prisma` | migration `Schema::create()` |
| Apply schema | `prisma migrate dev` / TypeORM sync | `php artisan migrate` |
| Model class | `@Entity` class with column decorators | `class Task extends Model` (no columns) |
| Insert | `repo.save(entity)` | `Task::create([...])` / `$task->save()` |
| Find or 404 | `repo.findOneOrFail()` + manual throw | `Task::findOrFail($id)` (auto 404) |
| Type conversion | `@Type(() => Date)` | `casts()` method |
| Input DTO | `class CreateTaskDto` + `class-validator` | `StoreTaskRequest` (`rules()`) |
| Run validation | `@UsePipes(ValidationPipe)` | type-hint the Form Request (automatic) |
| Authorize input | Guard / custom decorator | `authorize()` in the Form Request |
| Output DTO | response DTO + serialization interceptor | `TaskResource` (`toArray()`) |
| Seed data | custom faker script | Factory + Seeder |

**Gotchas for TypeScript Devs**
- **Mass assignment is a real security boundary, not boilerplate.** `Task::create($request->all())` lets a client set ANY column (e.g. `is_admin`, `user_id`) by adding it to the JSON body. `$fillable` whitelists what `create()`/`fill()` accept, and `$request->validated()` strips unknown keys. TypeORM has no equivalent footgun here — in Laravel this is on you.
- **The model has no compile-time knowledge of its columns.** `$task->titel` (typo) is **not** a compile error — it returns `null` silently. There's no TS type safety on attributes. Lean on IDE helpers (`php artisan ide-helper:models`) or tests; the compiler won't save you.
- **Dates come back as `Carbon` objects, not strings — but only if cast.** With `'due_at' => 'datetime'`, `$task->due_at` is a Carbon instance (`->toIso8601String()`, `->addDays(3)`). Without the cast it's a plain string. This is the opposite of Prisma where `DateTime` is always a JS `Date`. Forgetting the cast = calling date methods on a string → error.

**3-Line Command Summary**
```bash
php artisan make:model Task -mf                 # model + migration + factory
php artisan make:request StoreTaskRequest       # validation/DTO class
php artisan migrate                             # apply schema (migrate:fresh to reset)
```

---

> **Next:** Phase 3 — Business Logic & DI (Service classes + NestJS DI → Service Container & automatic resolution).
