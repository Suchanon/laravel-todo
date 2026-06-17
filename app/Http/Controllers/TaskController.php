<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * GET /api/tasks — list all tasks.
     */
    public function index()
    {
        return  response()->json([
            'data' => ['Learn routing', 'Build Task API'],
        ]);
    }

    /**
     * POST /api/tasks — create a task.
     */
    public function store(Request $request)
    {
        return response()->json(['received' => $request->all()], 201);
    }

    /**
     * GET /api/tasks/{task} — show one task.
     * Note: {task} from the route is injected by name, auto-resolved by the container.
     */
    public function show(string $id)
    {
        return response()->json(['data' => "Task #{$id}"]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
