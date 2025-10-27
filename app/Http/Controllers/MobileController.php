<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Models\AssemblyChecklist;
use App\Models\Project;
use App\Models\ArUcoMarker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileController extends Controller
{
    /**
     * Display the mobile app interface.
     */
    public function index()
    {
        return view('mobile.app');
    }

    /**
     * Get inventory data for mobile app.
     */
    public function inventory(Request $request): JsonResponse
    {
        $query = Component::with(['category'])
            ->where('quantity', '>', 0)
            ->orderBy('name');

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%")
                  ->orWhere('part_number', 'ILIKE', "%{$search}%");
            });
        }

        // Category filter
        if ($request->has('category') && $request->category) {
            $query->where('category_id', $request->category);
        }

        $components = $query->limit(50)->get()->map(function ($component) {
            return [
                'id' => $component->id,
                'name' => $component->name,
                'description' => $component->description,
                'part_number' => $component->part_number,
                'quantity' => $component->quantity,
                'unit_price' => $component->unit_price,
                'category' => $component->category->name ?? 'Uncategorized',
                'location' => $component->location,
                'datasheet_url' => $component->datasheet_url,
                'image_url' => $component->image_path ? asset('storage/' . $component->image_path) : null,
                'aruco_marker_id' => $component->aruco_marker_id,
                'last_updated' => $component->updated_at->toISOString(),
            ];
        });

        return response()->json($components);
    }

    /**
     * Get component by ID.
     */
    public function component($id): JsonResponse
    {
        $component = Component::with(['category', 'supplier'])->find($id);

        if (!$component) {
            return response()->json(['error' => 'Component not found'], 404);
        }

        return response()->json([
            'id' => $component->id,
            'name' => $component->name,
            'description' => $component->description,
            'part_number' => $component->part_number,
            'quantity' => $component->quantity,
            'unit_price' => $component->unit_price,
            'category' => $component->category->name ?? 'Uncategorized',
            'supplier' => $component->supplier->name ?? null,
            'location' => $component->location,
            'datasheet_url' => $component->datasheet_url,
            'image_url' => $component->image_path ? asset('storage/' . $component->image_path) : null,
            'specifications' => $component->specifications,
            'aruco_marker_id' => $component->aruco_marker_id,
            'last_updated' => $component->updated_at->toISOString(),
        ]);
    }

    /**
     * Get component by ArUco marker ID.
     */
    public function aruco($markerId): JsonResponse
    {
        // First check if it's a direct component ArUco marker
        $component = Component::where('aruco_marker_id', $markerId)->first();

        if ($component) {
            return $this->component($component->id);
        }

        // Check if it's a registered ArUco marker
        $marker = ArUcoMarker::where('marker_id', $markerId)->first();

        if (!$marker) {
            return response()->json(['error' => 'ArUco marker not found'], 404);
        }

        // Handle different marker types
        switch ($marker->type) {
            case 'component':
                if ($marker->component_id) {
                    return $this->component($marker->component_id);
                }
                break;

            case 'location':
                return response()->json([
                    'type' => 'location',
                    'location' => $marker->data['location'] ?? 'Unknown',
                    'description' => $marker->data['description'] ?? null,
                    'components' => Component::where('location', $marker->data['location'] ?? '')->count(),
                ]);

            case 'checklist':
                if ($marker->data['checklist_id'] ?? null) {
                    return $this->checklist($marker->data['checklist_id']);
                }
                break;

            case 'project':
                if ($marker->data['project_id'] ?? null) {
                    return $this->project($marker->data['project_id']);
                }
                break;
        }

        return response()->json([
            'type' => $marker->type,
            'marker_id' => $markerId,
            'data' => $marker->data,
            'created_at' => $marker->created_at->toISOString(),
        ]);
    }

    /**
     * Get active checklists for mobile app.
     */
    public function checklists(Request $request): JsonResponse
    {
        $query = AssemblyChecklist::with(['template', 'project', 'assignedUser'])
            ->whereIn('status', ['not_started', 'in_progress', 'on_hold'])
            ->orderBy('created_at', 'desc');

        // Filter by assigned user if provided
        if ($request->has('assigned_to') && $request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $checklists = $query->limit(20)->get()->map(function ($checklist) {
            return [
                'id' => $checklist->id,
                'template_name' => $checklist->template->name,
                'template_id' => $checklist->template_id,
                'project_id' => $checklist->project_id,
                'project_name' => $checklist->project->name ?? null,
                'board_serial_number' => $checklist->board_serial_number,
                'batch_number' => $checklist->batch_number,
                'board_quantity' => $checklist->board_quantity,
                'status' => $checklist->status,
                'assigned_to' => $checklist->assignedUser->name ?? null,
                'completion_percentage' => $checklist->completion_percentage,
                'total_items' => $checklist->total_items,
                'completed_items' => $checklist->completed_items,
                'failed_items' => $checklist->failed_items,
                'started_at' => $checklist->started_at?->toISOString(),
                'estimated_time_remaining' => $checklist->getEstimatedTimeRemaining(),
                'qr_code_url' => $checklist->generateQRCode(),
            ];
        });

        return response()->json($checklists);
    }

    /**
     * Get checklist by ID.
     */
    public function checklist($id): JsonResponse
    {
        $checklist = AssemblyChecklist::with([
            'template',
            'project',
            'assignedUser',
            'responses.item'
        ])->find($id);

        if (!$checklist) {
            return response()->json(['error' => 'Checklist not found'], 404);
        }

        $items = $checklist->template->items()
            ->orderBy('sort_order')
            ->get()
            ->map(function ($item) use ($checklist) {
                $response = $checklist->responses->where('item_id', $item->id)->first();
                
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'instructions' => $item->instructions,
                    'type' => $item->type,
                    'category' => $item->category,
                    'is_required' => $item->is_required,
                    'is_critical' => $item->is_critical,
                    'sort_order' => $item->sort_order,
                    'options' => $item->options,
                    'validation_rules' => $item->validation_rules,
                    'reference_image' => $item->reference_image ? asset('storage/' . $item->reference_image) : null,
                    'safety_notes' => $item->safety_notes,
                    'estimated_minutes' => $item->estimated_minutes,
                    'response' => $response ? [
                        'id' => $response->id,
                        'status' => $response->status,
                        'response_data' => $response->response_data,
                        'comments' => $response->comments,
                        'failure_reason' => $response->failure_reason,
                        'completed_at' => $response->completed_at?->toISOString(),
                        'formatted_response' => $response->getFormattedResponse(),
                        'attachments' => $response->getAttachmentUrls(),
                    ] : null,
                ];
            });

        return response()->json([
            'id' => $checklist->id,
            'template_name' => $checklist->template->name,
            'project_name' => $checklist->project->name ?? null,
            'board_serial_number' => $checklist->board_serial_number,
            'status' => $checklist->status,
            'completion_percentage' => $checklist->completion_percentage,
            'items' => $items,
            'completion_by_category' => $checklist->getCompletionByCategory(),
            'next_pending_item' => $checklist->getNextPendingItem(),
            'failed_items' => $checklist->getFailedItems(),
        ]);
    }

    /**
     * Update checklist item response.
     */
    public function updateChecklistResponse(Request $request, $checklistId, $itemId): JsonResponse
    {
        $checklist = AssemblyChecklist::find($checklistId);
        if (!$checklist) {
            return response()->json(['error' => 'Checklist not found'], 404);
        }

        $response = $checklist->responses()->where('item_id', $itemId)->first();
        if (!$response) {
            return response()->json(['error' => 'Response not found'], 404);
        }

        try {
            switch ($request->action) {
                case 'complete':
                    $response->complete($request->response_data, $request->comments);
                    break;

                case 'fail':
                    $response->markAsFailed($request->failure_reason, $request->comments);
                    break;

                case 'skip':
                    $response->skip($request->reason);
                    break;

                case 'review':
                    $response->markForReview($request->reason);
                    break;

                default:
                    return response()->json(['error' => 'Invalid action'], 400);
            }

            return response()->json([
                'success' => true,
                'response' => [
                    'id' => $response->id,
                    'status' => $response->status,
                    'response_data' => $response->response_data,
                    'comments' => $response->comments,
                    'formatted_response' => $response->getFormattedResponse(),
                ],
                'checklist_progress' => $checklist->completion_percentage,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get projects for mobile app.
     */
    public function projects(Request $request): JsonResponse
    {
        $query = Project::with(['customer'])
            ->whereIn('status', ['active', 'in_progress', 'on_hold'])
            ->orderBy('created_at', 'desc');

        $projects = $query->limit(20)->get()->map(function ($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'customer_name' => $project->customer->name ?? null,
                'start_date' => $project->start_date?->toISOString(),
                'end_date' => $project->end_date?->toISOString(),
                'progress_percentage' => $project->progress_percentage ?? 0,
                'total_cost' => $project->total_cost,
                'created_at' => $project->created_at->toISOString(),
            ];
        });

        return response()->json($projects);
    }

    /**
     * Get project by ID.
     */
    public function project($id): JsonResponse
    {
        $project = Project::with(['customer', 'tasks', 'documents'])->find($id);

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        return response()->json([
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'customer' => $project->customer ? [
                'id' => $project->customer->id,
                'name' => $project->customer->name,
                'email' => $project->customer->email,
            ] : null,
            'start_date' => $project->start_date?->toISOString(),
            'end_date' => $project->end_date?->toISOString(),
            'progress_percentage' => $project->progress_percentage ?? 0,
            'total_cost' => $project->total_cost,
            'tasks' => $project->tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'progress' => $task->progress,
                    'start_date' => $task->start_date?->toISOString(),
                    'end_date' => $task->end_date?->toISOString(),
                ];
            }),
            'documents' => $project->documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'name' => $document->original_name,
                    'type' => $document->file_type,
                    'size' => $document->file_size,
                    'url' => $document->getDownloadUrl(),
                ];
            }),
        ]);
    }

    /**
     * Record component scan/usage.
     */
    public function recordScan(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:aruco,qr,barcode',
            'data' => 'required|string',
            'action' => 'required|in:view,use,inventory_check',
            'component_id' => 'nullable|exists:components,id',
            'project_id' => 'nullable|exists:projects,id',
            'location' => 'nullable|string',
        ]);

        // Log the scan activity
        activity()
            ->withProperties([
                'scan_type' => $request->type,
                'scan_data' => $request->data,
                'action' => $request->action,
                'component_id' => $request->component_id,
                'project_id' => $request->project_id,
                'location' => $request->location,
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ])
            ->log('mobile_scan');

        return response()->json(['success' => true, 'timestamp' => now()->toISOString()]);
    }

    /**
     * Upload attachment for checklist response.
     */
    public function uploadAttachment(Request $request, $checklistId, $itemId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|in:photo,file,signature',
        ]);

        $checklist = AssemblyChecklist::find($checklistId);
        if (!$checklist) {
            return response()->json(['error' => 'Checklist not found'], 404);
        }

        $response = $checklist->responses()->where('item_id', $itemId)->first();
        if (!$response) {
            return response()->json(['error' => 'Response not found'], 404);
        }

        try {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('checklist-attachments', $filename, 'public');

            $response->addAttachment($path, $request->type);

            return response()->json([
                'success' => true,
                'attachment' => [
                    'path' => $path,
                    'url' => asset('storage/' . $path),
                    'type' => $request->type,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get system status and statistics.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'system' => [
                'status' => 'online',
                'version' => '1.0.0',
                'timestamp' => now()->toISOString(),
            ],
            'stats' => [
                'total_components' => Component::count(),
                'low_stock_components' => Component::where('quantity', '<=', 10)->count(),
                'active_checklists' => AssemblyChecklist::whereIn('status', ['not_started', 'in_progress'])->count(),
                'active_projects' => Project::whereIn('status', ['active', 'in_progress'])->count(),
            ],
        ]);
    }
}