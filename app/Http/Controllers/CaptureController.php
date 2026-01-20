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
        $captures = $request->user()->captures()
            ->with(['linksTo', 'linkedFrom'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($captures);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $capture = $request->user()->captures()->create($validated);
        
        // Load relationships
        $capture->load(['linksTo', 'linkedFrom']);

        return response()->json($capture, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $capture = Capture::where('user_id', $request->user()->id)
            ->with(['linksTo', 'linkedFrom'])
            ->findOrFail($id);
        return response()->json($capture);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'title' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $capture = Capture::where('user_id', $request->user()->id)->findOrFail($id);
        $capture->update($validated);
        
        // Load relationships
        $capture->load(['linksTo', 'linkedFrom']);

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

    /**
     * Search captures by query.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        
        if (empty($query)) {
            return response()->json([]);
        }

        $captures = Capture::where('user_id', $request->user()->id)
            ->where(function ($q) use ($query) {
                $q->where('title', 'ilike', "%{$query}%")
                  ->orWhere('content', 'ilike', "%{$query}%");
            })
            ->with(['linksTo', 'linkedFrom'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($captures);
    }

    /**
     * Get linked notes for a specific capture.
     */
    public function links(Request $request, string $id): JsonResponse
    {
        $capture = Capture::where('user_id', $request->user()->id)->findOrFail($id);
        
        $linkedNotes = $capture->linksTo()->with(['linksTo', 'linkedFrom'])->get();
        
        return response()->json($linkedNotes);
    }

    /**
     * Get graph data for visualization.
     */
    public function graph(Request $request): JsonResponse
    {
        $captures = Capture::where('user_id', $request->user()->id)
            ->with(['linksTo'])
            ->get();

        $nodes = [];
        $edges = [];
        $nodeMap = [];

        // Create nodes
        foreach ($captures as $index => $capture) {
            $nodeId = (string) $capture->id;
            $nodeMap[$capture->id] = $nodeId;
            
            $nodes[] = [
                'id' => $nodeId,
                'data' => [
                    'label' => $capture->title ?: mb_substr($capture->content, 0, 50),
                    'tags' => $capture->tags ?? [],
                    'captureId' => $capture->id,
                    'slug' => $capture->slug,
                ],
                'position' => [
                    'x' => ($index % 10) * 200,
                    'y' => floor($index / 10) * 200,
                ],
            ];
        }

        // Create edges
        $edgeId = 1;
        foreach ($captures as $capture) {
            foreach ($capture->linksTo as $linkedCapture) {
                $sourceId = $nodeMap[$capture->id];
                $targetId = $nodeMap[$linkedCapture->id];
                
                $edges[] = [
                    'id' => "e{$edgeId}",
                    'source' => $sourceId,
                    'target' => $targetId,
                ];
                $edgeId++;
            }
        }

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }
}
