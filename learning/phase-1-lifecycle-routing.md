# Phase 1: Lifecycle & Routing

> Part of the [Laravel 13 for NestJS Developers](LEARNING_JOURNEY.md) journey.
> **Core mental model:** NestJS is *explicit & declarative* (decorators everywhere). Laravel is *convention-driven & fluent*.

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
6. Hit `GET /api/tasks` and `GET /api/tasks/5` (via your Herd URL, e.g. `http://todo.test/api/tasks`) and confirm the responses, including that `{task}` correctly injects `5`.

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

> **Next:** [Phase 2 — Data Layer & Validation](phase-2-data-layer-validation.md)
