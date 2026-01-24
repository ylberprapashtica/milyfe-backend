<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCaptureMetadata;
use App\Models\Capture;
use App\Models\NoteLink;
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
        
        // Check if we need to generate metadata with AI
        $needsTitle = empty($validated['title']);
        $needsTags = empty($validated['tags']);
        
        // If title was not provided, it was auto-extracted from content
        // We should still generate an AI title to improve it
        if ($needsTitle) {
            $extractedTitle = Capture::extractTitleFromContent($validated['content']);
            // Check if the current title matches the auto-extracted one
            if ($capture->title === $extractedTitle) {
                $needsTitle = true;
            }
        }
        
        if ($needsTitle || $needsTags) {
            // Dispatch job to generate missing metadata asynchronously
            GenerateCaptureMetadata::dispatch($capture, $needsTitle, $needsTags);
        }
        
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
     * Update the graph position of a capture.
     */
    public function updatePosition(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'x' => 'required|numeric',
            'y' => 'required|numeric',
        ]);

        $capture = Capture::where('user_id', $request->user()->id)->findOrFail($id);
        $capture->update([
            'graph_x' => $validated['x'],
            'graph_y' => $validated['y'],
        ]);

        return response()->json([
            'message' => 'Position updated successfully',
            'capture' => $capture,
        ]);
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
            
            // Use saved position if available, otherwise generate default position
            $position = [
                'x' => ($index % 10) * 200,
                'y' => floor($index / 10) * 200,
            ];
            
            if ($capture->graph_x !== null && $capture->graph_y !== null) {
                $position = [
                    'x' => (float) $capture->graph_x,
                    'y' => (float) $capture->graph_y,
                ];
            }
            
            $nodes[] = [
                'id' => $nodeId,
                'data' => [
                    'label' => $capture->title ?: mb_substr($capture->content, 0, 50),
                    'tags' => $capture->tags ?? [],
                    'captureId' => $capture->id,
                    'slug' => $capture->slug,
                    'content' => $capture->content,
                    'updated_at' => $capture->updated_at,
                ],
                'position' => $position,
            ];
        }

        // Create edges
        $edgeId = 1;
        foreach ($captures as $capture) {
            foreach ($capture->linksTo as $linkedCapture) {
                $sourceId = $nodeMap[$capture->id];
                $targetId = $nodeMap[$linkedCapture->id];
                
                // Get the link ID from the pivot table
                $linkId = $linkedCapture->pivot->id ?? null;
                
                $edges[] = [
                    'id' => "e{$edgeId}",
                    'source' => $sourceId,
                    'target' => $targetId,
                    'linkId' => $linkId,
                ];
                $edgeId++;
            }
        }

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    /**
     * Create a link between two captures.
     */
    public function createLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_capture_id' => 'required|integer|exists:captures,id',
            'target_capture_id' => 'required|integer|exists:captures,id',
        ]);

        // Verify that both captures belong to the authenticated user
        $sourceCapture = Capture::where('id', $validated['source_capture_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$sourceCapture) {
            return response()->json([
                'message' => 'Source capture not found or does not belong to you.',
            ], 404);
        }

        $targetCapture = Capture::where('id', $validated['target_capture_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$targetCapture) {
            return response()->json([
                'message' => 'Target capture not found or does not belong to you.',
            ], 404);
        }

        // Prevent self-linking
        if ($validated['source_capture_id'] === $validated['target_capture_id']) {
            return response()->json([
                'message' => 'Cannot create a link from a capture to itself.',
            ], 422);
        }

        // Check if link already exists
        $existingLink = NoteLink::where('source_capture_id', $validated['source_capture_id'])
            ->where('target_capture_id', $validated['target_capture_id'])
            ->first();

        if ($existingLink) {
            // Reload the source capture with relationships
            $sourceCapture->load(['linksTo', 'linkedFrom']);
            
            return response()->json([
                'message' => 'Link already exists.',
                'link' => $existingLink,
                'source_capture' => $sourceCapture,
            ], 200);
        }

        // Create the link
        $link = NoteLink::create([
            'source_capture_id' => $validated['source_capture_id'],
            'target_capture_id' => $validated['target_capture_id'],
        ]);

        // Add wiki-link to source capture's content if not already present
        $wikiLink = "[[{$targetCapture->title}]]";
        if (strpos($sourceCapture->content, $wikiLink) === false) {
            // Append the wiki-link at the end of the content with a newline
            $sourceCapture->content = rtrim($sourceCapture->content) . "\n" . $wikiLink;
            $sourceCapture->save();
        }

        // Reload the source capture to get the updated data
        $sourceCapture->load(['linksTo', 'linkedFrom']);

        return response()->json([
            'message' => 'Link created successfully.',
            'link' => $link,
            'source_capture' => $sourceCapture,
        ], 201);
    }

    /**
     * Delete a link between two captures.
     */
    public function deleteLink(Request $request, string $linkId): JsonResponse
    {
        $link = NoteLink::find($linkId);

        if (!$link) {
            return response()->json([
                'message' => 'Link not found.',
            ], 404);
        }

        // Verify that the link belongs to the authenticated user
        // by checking that both source and target captures belong to the user
        $sourceCapture = Capture::where('id', $link->source_capture_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$sourceCapture) {
            return response()->json([
                'message' => 'Unauthorized to delete this link.',
            ], 403);
        }

        $targetCapture = Capture::where('id', $link->target_capture_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$targetCapture) {
            return response()->json([
                'message' => 'Unauthorized to delete this link.',
            ], 403);
        }

        // Delete the link
        $link->delete();

        return response()->json([
            'message' => 'Link deleted successfully.',
        ], 200);
    }
}
