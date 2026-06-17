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

    public function delete(Task $task): void{
        $task->delete();
    }
}
