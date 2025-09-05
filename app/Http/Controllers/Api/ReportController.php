<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportGeneratorService;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportGeneratorService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Generate a new report
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'report_type' => 'required|string|in:financial,operational,compliance,custom',
                'template_id' => 'nullable|integer|exists:report_templates,id',
                'parameters' => 'nullable|array',
                'format' => 'nullable|string|in:json,pdf,excel,csv',
                'schedule' => 'nullable|array',
                'notify_users' => 'nullable|array'
            ]);

            $report = $this->reportService->generateReport(
                $data['report_type'],
                $data['template_id'] ?? null,
                $data['parameters'] ?? [],
                $data['format'] ?? 'json',
                $data['schedule'] ?? null,
                $data['notify_users'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'data' => $report
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get generated reports
     */
    public function getGeneratedReports(Request $request): JsonResponse
    {
        try {
            $query = Report::query();

            // Apply filters
            if ($request->report_type) {
                $query->where('report_type', $request->report_type);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->generated_by) {
                $query->where('generated_by', $request->generated_by);
            }

            if ($request->date_from) {
                $query->where('created_at', '>=', Carbon::parse($request->date_from));
            }

            if ($request->date_to) {
                $query->where('created_at', '<=', Carbon::parse($request->date_to));
            }

            $reports = $query->with(['template', 'generatedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve generated reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report details
     */
    public function getReportDetails(Report $report): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $report->load(['template', 'generatedBy'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve report details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve report details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a report
     */
    public function deleteReport(Report $report): JsonResponse
    {
        try {
            // Delete associated file if exists
            if ($report->file_path && Storage::exists($report->file_path)) {
                Storage::delete($report->file_path);
            }

            $report->delete();

            return response()->json([
                'success' => true,
                'message' => 'Report deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report templates
     */
    public function getTemplates(Request $request): JsonResponse
    {
        try {
            $query = ReportTemplate::query();

            if ($request->report_type) {
                $query->where('report_type', $request->report_type);
            }

            if ($request->is_active !== null) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $templates = $query->orderBy('name')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve report templates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template details
     */
    public function getTemplateDetails(ReportTemplate $template): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new template
     */
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'report_type' => 'required|string|in:financial,operational,compliance,custom',
                'configuration' => 'required|array',
                'is_active' => 'nullable|boolean',
                'access_roles' => 'nullable|array'
            ]);

            $template = ReportTemplate::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a template
     */
    public function updateTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'configuration' => 'nullable|array',
                'is_active' => 'nullable|boolean',
                'access_roles' => 'nullable|array'
            ]);

            $template->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => $template
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(ReportTemplate $template): JsonResponse
    {
        try {
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial reports
     */
    public function getFinancialReports(Request $request): JsonResponse
    {
        try {
            $reports = Report::where('report_type', 'financial')
                ->with(['template', 'generatedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve financial reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve financial reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get operational reports
     */
    public function getOperationalReports(Request $request): JsonResponse
    {
        try {
            $reports = Report::where('report_type', 'operational')
                ->with(['template', 'generatedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve operational reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve operational reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance reports
     */
    public function getComplianceReports(Request $request): JsonResponse
    {
        try {
            $reports = Report::where('report_type', 'compliance')
                ->with(['template', 'generatedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve compliance reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve compliance reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get custom reports
     */
    public function getCustomReports(Request $request): JsonResponse
    {
        try {
            $reports = Report::where('report_type', 'custom')
                ->with(['template', 'generatedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve custom reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve custom reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule a report
     */
    public function scheduleReport(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'report_type' => 'required|string|in:financial,operational,compliance,custom',
                'template_id' => 'nullable|integer|exists:report_templates,id',
                'parameters' => 'nullable|array',
                'schedule_frequency' => 'required|string|in:daily,weekly,monthly',
                'schedule_time' => 'required|date_format:H:i',
                'schedule_day' => 'nullable|string',
                'notify_users' => 'nullable|array',
                'format' => 'nullable|string|in:json,pdf,excel,csv'
            ]);

            $schedule = $this->reportService->scheduleReport($data);

            return response()->json([
                'success' => true,
                'message' => 'Report scheduled successfully',
                'data' => $schedule
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to schedule report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scheduled reports
     */
    public function getScheduledReports(Request $request): JsonResponse
    {
        try {
            $schedules = $this->reportService->getScheduledReports($request->all());

            return response()->json([
                'success' => true,
                'data' => $schedules
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve scheduled reports: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scheduled reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a scheduled report
     */
    public function cancelScheduledReport($scheduleId): JsonResponse
    {
        try {
            $this->reportService->cancelScheduledReport($scheduleId);

            return response()->json([
                'success' => true,
                'message' => 'Scheduled report cancelled successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel scheduled report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel scheduled report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export a report
     */
    public function exportReport(Report $report): JsonResponse
    {
        try {
            $exportData = $this->reportService->exportReport($report);

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a report file
     */
    public function downloadReport(Report $report): JsonResponse
    {
        try {
            if (!$report->file_path || !Storage::exists($report->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Report file not found'
                ], 404);
            }

            $downloadUrl = Storage::url($report->file_path);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $downloadUrl,
                    'file_name' => $report->file_name,
                    'file_size' => Storage::size($report->file_path),
                    'mime_type' => Storage::mimeType($report->file_path)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to download report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 