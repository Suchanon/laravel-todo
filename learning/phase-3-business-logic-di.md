# Phase 3: Business Logic & DI

> Part of the [Laravel 13 for NestJS Developers](LEARNING_JOURNEY.md) journey. Previous: [Phase 2](phase-2-data-layer-validation.md).

## 1. The Conceptual Bridge

| NestJS Concept | Laravel 13 Equivalent | Note |
|---|---|---|
| `@Injectable()` service class | A plain PHP class (no attribute needed) | Just a class — the container resolves it |
| Constructor injection | Constructor injection (identical) | Type-hint → auto-resolved |
| NestJS IoC container | **Service Container** (`app()`) | Same role |
| `@Module({ providers: [...] })` registration | **Nothing** for concrete classes (autowiring) | Zero registration needed |
| `{ provide: TOKEN, useClass: Impl }` | `$app->bind(Interface::class, Impl::class)` | For interface → impl |
| Custom provider / `useValue` | `$app->singleton()` / `$app->instance()` | In a Service Provider |
| `@Inject(TOKEN)` | contextual binding / bind by interface | Token-style injection |
| Provider scope (singleton default) | `bind` = new each time, `singleton` = shared | **Default differs! see gotchas** |

> **The shift:** NestJS forces you to *register every provider* in a module's `providers` array. Laravel **autowires concrete classes for free** — if you type-hint `TaskService` anywhere the container builds it, recursively resolving its own constructor dependencies. You only touch a Service Provider when you need to bind an **interface** to an implementation, or control lifecycle.

---

## 2. A Service Class (the `@Injectable()` equivalent)

No `@Injectable()`, no decorator, no registration. Just a class:

```bash
php artisan make:class Services/TaskService
```

```php
<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    /**
     * @return Collection<int, Task>
     */
    public function all(): Collection
    {
        return Task::query()->latest()->get();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Task
    {
        return Task::create($data)->refresh(); // refresh() pulls DB defaults (e.g. is_completed)
    }

    public function toggleComplete(Task $task): Task
    {
        $task->update(['is_completed' => ! $task->is_completed]);

        return $task;
    }
}
```

---

## 3. Injecting It — Constructor Promotion

This is *identical* to NestJS constructor injection, using PHP 8 promoted properties:

```php
class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks, // type-hint = autowired by the container
    ) {}

    public function index()
    {
        return TaskResource::collection($this->tasks->all());
    }

    public function store(StoreTaskRequest $request)
    {
        $task = $this->tasks->create($request->validated());

        return (new TaskResource($task))->response()->setStatusCode(201);
    }
}
```

You did **not** register `TaskService` anywhere. When Laravel builds the controller, it sees the type-hint, instantiates `TaskService`, and (if `TaskService` had its own constructor deps) resolves those too — recursively. That's the whole DI graph, free.

You can also inject **per-method** (no NestJS equivalent — handy for one-off deps):

```php
public function destroy(Task $task, TaskService $tasks) // both route-bound Task AND injected service
{
    $tasks->delete($task);

    return response()->noContent(); // 204
}
```

---

## 4. Interface → Implementation Binding (Service Providers)

When you want to depend on an **interface** (Dependency Inversion) — the equivalent of NestJS `{ provide: NotifierInterface, useClass: SlackNotifier }` — you bind it in a Service Provider's `register()`:

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        \App\Contracts\TaskNotifier::class,
        \App\Notifications\SlackTaskNotifier::class,
    );
}
```

Now type-hinting `TaskNotifier` anywhere injects a `SlackTaskNotifier`. Swap the impl in one line — exactly NestJS custom providers, minus the per-module wiring.

**Lifecycle control:**

```php
$this->app->bind(X::class, Y::class);       // NEW instance every resolve (transient)
$this->app->singleton(X::class, Y::class);  // ONE shared instance (like NestJS default)
$this->app->instance(X::class, $object);    // use this exact pre-built object (useValue)
```

> `register()` = only bind things into the container (no using other services yet). `boot()` = runs after ALL providers registered, safe to use other services. This is the two-phase lifecycle, like NestJS `onModuleInit` ordering.

---

## 🎯 Micro-Assignment (complete before Phase 4)

1. `php artisan make:class Services/TaskService`.
2. Move your task logic (`all`, `create`, plus add `update` and `delete`) out of the controller into `TaskService`.
3. Inject `TaskService` via the controller constructor (promoted `private readonly`).
4. Make `index`, `store`, `update`, `destroy` delegate to the service. Controller methods should be ~1–2 lines each.
5. Confirm every endpoint still returns the same responses (re-run your REST client / curl).
6. Implement `destroy` → `204 No Content` and `update` → returns the updated `TaskResource`.

**Stretch:** Define an interface `App\Contracts\TaskRepository`, make `TaskService` depend on it via constructor, bind it to an Eloquent implementation in `AppServiceProvider::register()`, and confirm injection still works. Then `dd(app(TaskService::class))` in a route to *see* the resolved graph.

---

### 📝 [Phase 3] Developer Log & Reference Notes

**Side-by-Side Syntax Reference**

| Concern | NestJS | Laravel 13 |
|---|---|---|
| Declare a service | `@Injectable() class X {}` | `class X {}` (plain class) |
| Register it | add to `@Module.providers` | nothing (autowired) |
| Constructor inject | `constructor(private x: X) {}` | `public function __construct(private X $x) {}` |
| Bind interface→impl | `{ provide: I, useClass: Impl }` | `$app->bind(I::class, Impl::class)` |
| Singleton | default scope | `$app->singleton(...)` (NOT default) |
| Fixed value provider | `{ provide: T, useValue: v }` | `$app->instance(T::class, $v)` |
| Resolve manually | `moduleRef.get(X)` | `app(X::class)` / `resolve(X::class)` |
| Init hook | `onModuleInit()` | provider `boot()` |
| Where bindings live | feature `*.module.ts` | `app/Providers/*ServiceProvider.php` |

**Gotchas for TypeScript Devs**
- **Default scope is INVERTED.** NestJS providers are **singletons by default** (one instance app-wide). Laravel's `bind()` gives a **fresh instance on every resolve**. If you keep state in a service expecting it to persist across injections, use `singleton()` explicitly — otherwise you'll get a new object each time and silently lose state.
- **Autowiring only works for *concrete* classes.** A type-hinted concrete class (`TaskService`) resolves automatically. An **interface** has no implementation to guess — if you forget to `bind()` it, you get a `BindingResolutionException` at runtime (not compile time). NestJS catches missing providers at bootstrap; Laravel fails on first resolve.
- **No `forwardRef()` needed, but circular deps still bite.** PHP resolves lazily, so you rarely hit Nest-style circular-dependency errors — but two services constructor-injecting each other will infinite-loop the container. Break the cycle by injecting one lazily via `app(X::class)` inside a method instead of the constructor.

**3-Line Command Summary**
```bash
php artisan make:class Services/TaskService      # plain service class (no decorator)
php artisan make:provider DomainServiceProvider  # only if you need custom bindings
php artisan tinker --execute 'dd(app(App\Services\TaskService::class));'  # inspect resolution
```

---

> **Next:** [Phase 4 — Security (Auth & Authorization)](phase-4-security.md)
