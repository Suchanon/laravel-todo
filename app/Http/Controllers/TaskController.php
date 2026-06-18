<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use Illuminate\Http\Request;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;

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
    public function show(Task $task)
    {
        return new TaskResource($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Task $task)
    {
        $updateResult = $this->tasks->toggleComplete($task);
        return new TaskResource($updateResult);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task) // both route-bound Task AND injected service
    {
        $this->tasks->delete($task);
        return response()->noContent(); //204
    }
}
