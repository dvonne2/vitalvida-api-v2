<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\InvestorDocument;
use App\Models\DocumentCategory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class DocumentController extends Controller
{
    /**
     * Get all documents for investor
     */
    public function getDocuments(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            $documents = InvestorDocument::with('category')
                ->where(function ($query) use ($investor) {
                    $query->whereNull('access_permissions')
                          ->orWhereJsonContains('access_permissions', $investor->role);
                })
                ->orderBy('priority', 'desc')
                ->orderBy('due_date', 'asc')
                ->get()
                ->map(function ($document) use ($investor) {
                    return [
                        'id' => $document->id,
                        'title' => $document->title,
                        'description' => $document->description,
                        'category' => [
                            'id' => $document->category->id,
                            'name' => $document->category->name,
                            'icon' => $document->category->getIconClass(),
                            'color' => $document->category->getColor()
                        ],
                        'status' => $document->status,
                        'status_display' => $document->getStatusDisplayName(),
                        'completion_status' => $document->completion_status,
                        'completion_status_display' => $document->getCompletionStatusDisplayName(),
                        'priority' => $document->priority,
                        'priority_display' => $document->getPriorityDisplayName(),
                        'due_date' => $document->due_date?->format('Y-m-d'),
                        'completed_date' => $document->completed_date?->format('Y-m-d'),
                        'is_overdue' => $document->isOverdue(),
                        'is_required' => $document->is_required,
                        'file_size' => $document->getFileSizeFormatted(),
                        'can_view' => $document->canBeViewedBy($investor),
                        'can_download' => $document->canBeDownloadedBy($investor),
                        'progress_percentage' => $document->getProgressPercentage(),
                        'assigned_to' => $document->assignedTo?->name,
                        'notes' => $document->notes
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document by ID
     */
    public function getDocument(Request $request, $id): JsonResponse
    {
        try {
            $investor = $request->user();
            
            $document = InvestorDocument::with('category', 'assignedTo', 'createdBy')
                ->findOrFail($id);

            if (!$document->isAccessibleByInvestor($investor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this document'
                ], 403);
            }

            $data = [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'category' => [
                    'id' => $document->category->id,
                    'name' => $document->category->name,
                    'description' => $document->category->description,
                    'icon' => $document->category->getIconClass(),
                    'color' => $document->category->getColor()
                ],
                'status' => $document->status,
                'status_display' => $document->getStatusDisplayName(),
                'completion_status' => $document->completion_status,
                'completion_status_display' => $document->getCompletionStatusDisplayName(),
                'priority' => $document->priority,
                'priority_display' => $document->getPriorityDisplayName(),
                'due_date' => $document->due_date?->format('Y-m-d'),
                'completed_date' => $document->completed_date?->format('Y-m-d'),
                'is_overdue' => $document->isOverdue(),
                'is_required' => $document->is_required,
                'file_info' => [
                    'name' => $document->file_name,
                    'type' => $document->file_type,
                    'size' => $document->getFileSizeFormatted(),
                    'url' => $document->getFileUrl()
                ],
                'access_permissions' => $document->access_permissions,
                'assigned_to' => $document->assignedTo?->name,
                'created_by' => $document->createdBy?->name,
                'created_at' => $document->created_at->format('M j, Y g:i A'),
                'updated_at' => $document->updated_at->format('M j, Y g:i A'),
                'notes' => $document->notes,
                'can_view' => $document->canBeViewedBy($investor),
                'can_download' => $document->canBeDownloadedBy($investor),
                'progress_percentage' => $document->getProgressPercentage()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download document
     */
    public function downloadDocument(Request $request, $id): JsonResponse
    {
        try {
            $investor = $request->user();
            
            $document = InvestorDocument::findOrFail($id);

            if (!$document->canBeDownloadedBy($investor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to download this document'
                ], 403);
            }

            if (!$document->file_path || !Storage::exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found'
                ], 404);
            }

            // Log download activity
            $this->logDocumentDownload($investor, $document);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $document->getFileUrl(),
                    'file_name' => $document->file_name,
                    'file_size' => $document->getFileSizeFormatted(),
                    'download_timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document categories
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            $categories = DocumentCategory::active()
                ->ordered()
                ->with(['documents' => function ($query) use ($investor) {
                    $query->where(function ($q) use ($investor) {
                        $q->whereNull('access_permissions')
                          ->orWhereJsonContains('access_permissions', $investor->role);
                    });
                }])
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'icon' => $category->getIconClass(),
                        'color' => $category->getColor(),
                        'progress' => $category->getProgressData(),
                        'document_count' => $category->getDocumentCount(),
                        'is_required_for_investor' => $category->isRequiredForInvestorType($investor->role)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load document categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get documents by category
     */
    public function getDocumentsByCategory(Request $request, $categoryId): JsonResponse
    {
        try {
            $investor = $request->user();
            
            $category = DocumentCategory::with(['documents' => function ($query) use ($investor) {
                $query->where(function ($q) use ($investor) {
                    $q->whereNull('access_permissions')
                      ->orWhereJsonContains('access_permissions', $investor->role);
                });
            }])
            ->findOrFail($categoryId);

            $documents = $category->documents->map(function ($document) use ($investor) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'description' => $document->description,
                    'status' => $document->status,
                    'status_display' => $document->getStatusDisplayName(),
                    'completion_status' => $document->completion_status,
                    'completion_status_display' => $document->getCompletionStatusDisplayName(),
                    'priority' => $document->priority,
                    'priority_display' => $document->getPriorityDisplayName(),
                    'due_date' => $document->due_date?->format('Y-m-d'),
                    'is_overdue' => $document->isOverdue(),
                    'is_required' => $document->is_required,
                    'can_view' => $document->canBeViewedBy($investor),
                    'can_download' => $document->canBeDownloadedBy($investor),
                    'progress_percentage' => $document->getProgressPercentage()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'category' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'icon' => $category->getIconClass(),
                        'color' => $category->getColor(),
                        'progress' => $category->getProgressData()
                    ],
                    'documents' => $documents
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load documents by category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search documents
     */
    public function searchDocuments(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            $query = $request->get('q', '');
            $categoryId = $request->get('category_id');
            $status = $request->get('status');
            $completionStatus = $request->get('completion_status');

            $documentsQuery = InvestorDocument::with('category')
                ->where(function ($q) use ($investor) {
                    $q->whereNull('access_permissions')
                      ->orWhereJsonContains('access_permissions', $investor->role);
                });

            if ($query) {
                $documentsQuery->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            }

            if ($categoryId) {
                $documentsQuery->where('category_id', $categoryId);
            }

            if ($status) {
                $documentsQuery->where('status', $status);
            }

            if ($completionStatus) {
                $documentsQuery->where('completion_status', $completionStatus);
            }

            $documents = $documentsQuery->orderBy('priority', 'desc')
                ->orderBy('due_date', 'asc')
                ->get()
                ->map(function ($document) use ($investor) {
                    return [
                        'id' => $document->id,
                        'title' => $document->title,
                        'description' => $document->description,
                        'category' => [
                            'id' => $document->category->id,
                            'name' => $document->category->name,
                            'icon' => $document->category->getIconClass(),
                            'color' => $document->category->getColor()
                        ],
                        'status' => $document->status,
                        'status_display' => $document->getStatusDisplayName(),
                        'completion_status' => $document->completion_status,
                        'completion_status_display' => $document->getCompletionStatusDisplayName(),
                        'priority' => $document->priority,
                        'priority_display' => $document->getPriorityDisplayName(),
                        'due_date' => $document->due_date?->format('Y-m-d'),
                        'is_overdue' => $document->isOverdue(),
                        'can_view' => $document->canBeViewedBy($investor),
                        'can_download' => $document->canBeDownloadedBy($investor),
                        'progress_percentage' => $document->getProgressPercentage()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'documents' => $documents,
                    'search_query' => $query,
                    'total_results' => $documents->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log document download activity
     */
    private function logDocumentDownload($investor, $document)
    {
        // Log the download activity for audit purposes
        // This could be stored in a separate audit log table
        \Log::info('Document downloaded', [
            'investor_id' => $investor->id,
            'investor_name' => $investor->name,
            'document_id' => $document->id,
            'document_title' => $document->title,
            'download_timestamp' => now()->toISOString(),
            'ip_address' => request()->ip()
        ]);
    }
}
