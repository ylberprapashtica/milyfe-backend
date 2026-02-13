<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    /**
     * Display a listing of the user's projects.
     */
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()
            ->orderBy('name')
            ->get();
        return response()->json($projects);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $project = $request->user()->projects()->create($validated);

        return response()->json($project, 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($project);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:2000',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Update the graph layout of the specified project (position and dimensions).
     */
    public function updateLayout(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'x' => 'required|numeric',
            'y' => 'required|numeric',
            'width' => 'required|numeric',
            'height' => 'required|numeric',
        ]);

        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $project->update([
            'graph_x' => $validated['x'],
            'graph_y' => $validated['y'],
            'graph_width' => $validated['width'],
            'graph_height' => $validated['height'],
        ]);

        return response()->json($project);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);

        // Set project_id to null for captures in this project
        $project->captures()->update(['project_id' => null]);

        $project->delete();

        return response()->json(null, 204);
    }
}
