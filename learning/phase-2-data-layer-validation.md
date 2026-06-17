# Phase 2: Data Layer & Validation

> Part of the [Laravel 13 for NestJS Developers](LEARNING_JOURNEY.md) journey. Previous: [Phase 1](phase-1-lifecycle-routing.md).

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
return new TaskResource($task);            // single → wraps under "data"
return TaskResource::collection($tasks);   // list → wraps under "data"
```

> ⚠️ A Resource auto-wraps under a `"data"` key. Don't also write `['data' => ...]` yourself, or you'll nest `data.data`. To return `201` from `store()`, convert it: `(new TaskResource($task))->response()->setStatusCode(201)`.

---

## 🎯 Micro-Assignment (complete before Phase 3)

1. `php artisan make:model Task -mf` and fill the migration with the columns above.
2. Run `php artisan migrate` and confirm the `tasks` table exists (`php artisan db:show` or the `database-schema` tool).
3. Set `$fillable` + `casts()` on the model.
4. `make:request StoreTaskRequest` with the rules above.
5. Replace your hardcoded `store()` with the Form Request version that does `Task::create($request->validated())`.
6. `POST /api/tasks` with `{"title":"Test"}` → expect `201` + the created row. Then POST `{}` (no title) → expect a `422` with a validation error for `title`.
7. Update `index()` to return `Task::all()` and `show()` to use Route Model Binding (`show(Task $task)`) — auto `404`.

**Stretch:** Build `TaskResource`, return it from `store()`/`index()`/`show()`, and confirm `is_completed` comes back as the renamed `completed` boolean.

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
| Route → model | manual `findOne(params.id)` | Route Model Binding: `show(Task $task)` |
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
- **DB defaults don't appear on a freshly created model.** `Task::create(['title' => 'x'])` returns an in-memory object where `is_completed` is `null`, not the DB's `false`. Call `->refresh()` to pull the real stored values.

**3-Line Command Summary**
```bash
php artisan make:model Task -mf                 # model + migration + factory
php artisan make:request StoreTaskRequest       # validation/DTO class
php artisan migrate                             # apply schema (migrate:fresh to reset)
```

---

> **Next:** [Phase 3 — Business Logic & DI](phase-3-business-logic-di.md)
