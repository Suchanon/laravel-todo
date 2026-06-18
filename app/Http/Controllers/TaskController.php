<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {}

    /**
     * GET /api/tasks — list all tasks.
     */
    public function index(Request $request)
    {
        return TaskResource::collection($request->user()->tasks()->latest()->get());
    }

    /**
     * POST /api/tasks — create a task.
     */
    public function store(StoreTaskRequest $request)
    {
        $task = $request->user()->tasks()->create($request->validated());

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/tasks/{task} — show one task.
     * Note: {task} from the route is injected by name, auto-resolved by the container.
     */
    #[Authorize('view', 'task')]
    public function show(Task $task)
    {
        return new TaskResource($task);
    }

    /**
     * Update the specified resource in storage.
     */
    #[Authorize('update', 'task')]
    public function update(Task $task)
    {
        $updateResult = $this->tasks->toggleComplete($task);

        return new TaskResource($updateResult);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[Authorize('delete', 'task')]
    public function destroy(Task $task) // both route-bound Task AND injected service
    {
        $this->tasks->delete($task);

        return response()->noContent(); // 204
    }
}
