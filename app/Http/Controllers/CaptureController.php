<?php

namespace App\Http\Controllers;

use App\Models\Capture;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CaptureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $captures = Capture::all();
        return response()->json($captures);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thought' => 'required|string',
        ]);

        $capture = Capture::create($validated);

        return response()->json($capture, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $capture = Capture::findOrFail($id);
        return response()->json($capture);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'thought' => 'required|string',
        ]);

        $capture = Capture::findOrFail($id);
        $capture->update($validated);

        return response()->json($capture);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $capture = Capture::findOrFail($id);
        $capture->delete();

        return response()->json(null, 204);
    }
}
