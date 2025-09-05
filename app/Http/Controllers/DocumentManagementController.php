<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Investor;
use App\Models\InvestorDocument;
use App\Models\DocumentCategory;
use App\Models\InvestorSession;
use Carbon\Carbon;

class DocumentManagementController extends Controller
{
    /**
     * Upload document with security validation
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            $request->validate([
                'category' => 'required|string|in:financials,operations,governance,vision,oversight',
                'title' => 'required|string|max:255',
                'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:10240', // 10MB max
                'required_for_investors' => 'required|array',
                'confidentiality' => 'required|string|in:public,restricted,confidential'
            ]);

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = 'investor-documents/' . $request->input('category') . '/' . $fileName;

            // Store file securely
            Storage::disk('private')->put($filePath, file_get_contents($file));

            // Create document record
            $document = InvestorDocument::create([
                'investor_id' => $investor->id,
                'document_category_id' => DocumentCategory::where('name', $request->input('category'))->first()->id,
                'title' => $request->input('title'),
                'filename' => $fileName,
                'file_path' => $filePath,
                'status' => 'complete',
                'completion_status' => 'ready',
                'description' => $request->input('description', ''),
                'version' => '1.0',
                'uploaded_by' => $investor->id,
                'is_required' => true,
                'is_confidential' => $request->input('confidentiality') === 'confidential'
            ]);

            // Log upload activity
            Log::info('Document uploaded', [
                'investor_id' => $investor->id,
                'document_id' => $document->id,
                'category' => $request->input('category'),
                'confidentiality' => $request->input('confidentiality'),
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'data' => [
                    'document_id' => $document->id,
                    'title' => $document->title,
                    'file_path' => $filePath,
                    'uploaded_at' => $document->created_at->toISOString(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'investor_id' => $investor->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document access log with audit trail
     */
    public function getAccessLog(Request $request, int $document_id): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            $document = InvestorDocument::findOrFail($document_id);

            // Check if investor has access to this document
            if (!$this->canAccessDocument($investor, $document)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this document.'
                ], 403);
            }

            // Get access log (simulated - in real app, this would be from a separate table)
            $accessLog = [
                [
                    'investor_id' => $investor->id,
                    'investor_name' => $investor->name,
                    'action' => 'viewed',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'accessed_at' => now()->subMinutes(5)->toISOString()
                ],
                [
                    'investor_id' => 2,
                    'investor_name' => 'Tomi Governance',
                    'action' => 'downloaded',
                    'ip_address' => '192.168.1.100',
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                    'accessed_at' => now()->subHours(2)->toISOString()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'document_id' => $document_id,
                    'document_title' => $document->title,
                    'access_log' => $accessLog,
                    'total_accesses' => count($accessLog),
                    'last_accessed' => $accessLog[0]['accessed_at'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access log',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk export documents
     */
    public function bulkExport(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized.'
                ], 401);
            }

            $request->validate([
                'document_ids' => 'required|array',
                'format' => 'required|string|in:zip,pdf',
                'include_metadata' => 'boolean'
            ]);

            $documentIds = $request->input('document_ids');
            $format = $request->input('format');
            $includeMetadata = $request->input('include_metadata', true);

            // Get documents that investor has access to
            $documents = InvestorDocument::whereIn('id', $documentIds)
                ->whereHas('category', function ($query) use ($investor) {
                    $query->whereJsonContains('required_for_investor_type', $investor->role);
                })
                ->get();

            if ($documents->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No accessible documents found for export.'
                ], 404);
            }

            // Simulate bulk export process
            $exportFileName = 'investor_documents_' . $investor->id . '_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            $exportPath = 'exports/' . $exportFileName;

            // In a real implementation, this would:
            // 1. Create a ZIP file with all documents
            // 2. Add metadata file if requested
            // 3. Generate a secure download link
            // 4. Log the export activity

            Log::info('Bulk document export initiated', [
                'investor_id' => $investor->id,
                'document_count' => $documents->count(),
                'format' => $format,
                'export_path' => $exportPath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk export initiated successfully.',
                'data' => [
                    'export_id' => uniqid('export_'),
                    'file_name' => $exportFileName,
                    'download_url' => Storage::url($exportPath),
                    'document_count' => $documents->count(),
                    'estimated_completion' => '2-3 minutes',
                    'status' => 'processing'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk export failed', [
                'error' => $e->getMessage(),
                'investor_id' => $investor->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate bulk export',
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
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $categories = [
                [
                    'id' => 1,
                    'name' => 'financials',
                    'display_name' => 'Financial Documents',
                    'description' => 'Financial statements, P&L, balance sheets, cash flow',
                    'required_documents' => 8,
                    'completed_documents' => 7,
                    'completion_percentage' => 87.5,
                    'documents' => [
                        'profit_loss_statement' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'balance_sheet' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'cash_flow_statement' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'financial_projections' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'audit_reports' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'tax_returns' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'bank_statements' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'investment_agreements' => ['status' => 'in_progress', 'last_updated' => '2024-12-07']
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'operations_systems',
                    'display_name' => 'Operations & Systems',
                    'description' => 'Operational procedures, system documentation, process flows',
                    'required_documents' => 6,
                    'completed_documents' => 5,
                    'completion_percentage' => 83.3,
                    'documents' => [
                        'operational_manual' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'system_documentation' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'process_flows' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'quality_control_procedures' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'inventory_management' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'supply_chain_documentation' => ['status' => 'in_progress', 'last_updated' => '2024-12-07']
                    ]
                ],
                [
                    'id' => 3,
                    'name' => 'governance_legal',
                    'display_name' => 'Governance & Legal',
                    'description' => 'Corporate governance, legal documents, compliance',
                    'required_documents' => 7,
                    'completed_documents' => 6,
                    'completion_percentage' => 85.7,
                    'documents' => [
                        'articles_of_incorporation' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'shareholder_agreements' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'board_resolutions' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'compliance_certificates' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'legal_opinions' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'regulatory_filings' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'intellectual_property' => ['status' => 'in_progress', 'last_updated' => '2024-12-07']
                    ]
                ],
                [
                    'id' => 4,
                    'name' => 'vision_strategy',
                    'display_name' => 'Vision & Strategy',
                    'description' => 'Business plan, strategic documents, market analysis',
                    'required_documents' => 5,
                    'completed_documents' => 4,
                    'completion_percentage' => 80.0,
                    'documents' => [
                        'business_plan' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'strategic_plan' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'market_analysis' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'competitive_analysis' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'growth_strategy' => ['status' => 'in_progress', 'last_updated' => '2024-12-07']
                    ]
                ],
                [
                    'id' => 5,
                    'name' => 'owner_oversight',
                    'display_name' => 'Owner Oversight',
                    'description' => 'Owner controls, financial oversight, risk management',
                    'required_documents' => 4,
                    'completed_documents' => 4,
                    'completion_percentage' => 100.0,
                    'documents' => [
                        'financial_controls' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'risk_management' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'internal_controls' => ['status' => 'complete', 'last_updated' => '2024-12-08'],
                        'oversight_procedures' => ['status' => 'complete', 'last_updated' => '2024-12-08']
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'categories' => $categories,
                    'summary' => [
                        'total_categories' => count($categories),
                        'total_documents' => array_sum(array_column($categories, 'required_documents')),
                        'completed_documents' => array_sum(array_column($categories, 'completed_documents')),
                        'overall_completion' => round(array_sum(array_column($categories, 'completed_documents')) / array_sum(array_column($categories, 'required_documents')) * 100, 1)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get document categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if investor can access a specific document
     */
    private function canAccessDocument(Investor $investor, InvestorDocument $document): bool
    {
        // Check if document is required for this investor type
        $requiredForInvestors = $document->category->required_for_investor_type ?? [];
        
        if (empty($requiredForInvestors)) {
            return true; // Document is accessible to all
        }

        return in_array($investor->role, $requiredForInvestors);
    }
}
