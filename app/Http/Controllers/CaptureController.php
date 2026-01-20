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
    public function index(Request $request): JsonResponse
    {
        $captures = $request->user()->captures;
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

        $capture = $request->user()->captures()->create($validated);

        return response()->json($capture, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $capture = Capture::where('user_id', $request->user()->id)->findOrFail($id);
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

        $capture = Capture::where('user_id', $request->user()->id)->findOrFail($id);
        $capture->update($validated);

        return response()->json($capture);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $capture = Capture::where('user_id', $request->user()->id)->findOrFail($id);
        $capture->delete();

        return response()->json(null, 204);
    }
}
