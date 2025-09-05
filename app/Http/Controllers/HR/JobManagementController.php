<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Models\JobApplication;
use App\Models\Department;
use App\Models\Position;
use App\Services\AIScreeningService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class JobManagementController extends Controller
{
    protected $aiScreeningService;

    public function __construct(AIScreeningService $aiScreeningService)
    {
        $this->aiScreeningService = $aiScreeningService;
    }

    /**
     * Get job management dashboard overview
     */
    public function getDashboard(): JsonResponse
    {
        try {
            $activeJobs = JobPosting::where('status', 'active')->count();
            $draftJobs = JobPosting::where('status', 'draft')->count();
            $totalApplications = JobApplication::count();
            
            // Calculate completion rate
            $completedApplications = JobApplication::whereIn('status', ['hired', 'rejected', 'withdrawn'])->count();
            $completionRate = $totalApplications > 0 ? round(($completedApplications / $totalApplications) * 100, 1) : 0;
            
            // Get job listings with detailed information
            $jobListings = JobPosting::with(['department', 'position'])
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get()
                ->map(function ($job) {
                    $applications = JobApplication::where('job_posting_id', $job->id)->count();
                    $filled = JobApplication::where('job_posting_id', $job->id)
                        ->where('status', 'hired')
                        ->count();
                    
                    return [
                        'id' => $job->id,
                        'title' => $job->title,
                        'department' => $job->department->name ?? 'Unknown',
                        'location' => $job->location ?? 'Not specified',
                        'type' => ucfirst(str_replace('_', ' ', $job->type)),
                        'applications' => $applications,
                        'filled' => $filled,
                        'status' => $job->status,
                        'skills' => json_decode($job->required_skills ?? '[]', true) ?? [],
                        'salary_range' => $this->formatSalaryRange($job->min_salary, $job->max_salary),
                        'actions' => $this->getJobActions($job),
                        'created_at' => $job->created_at->format('M j, Y'),
                        'application_deadline' => $job->application_deadline ? $job->application_deadline->format('M j, Y') : null
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'active_jobs' => $activeJobs,
                        'total_applications' => $totalApplications,
                        'draft_jobs' => $draftJobs,
                        'completion_rate' => $completionRate . '%'
                    ],
                    'job_listings' => $jobListings
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Job Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load job dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new job posting
     */
    public function createJob(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'department_id' => 'required|exists:departments,id',
                'position_id' => 'required|exists:positions,id',
                'location' => 'nullable|string|max:255',
                'min_salary' => 'nullable|numeric|min:0',
                'max_salary' => 'nullable|numeric|min:0',
                'description' => 'required|string',
                'requirements' => 'nullable|string',
                'responsibilities' => 'nullable|string',
                'benefits' => 'nullable|string',
                'type' => 'required|in:full_time,part_time,contract,intern,freelance',
                'vacancies' => 'required|integer|min:1',
                'application_deadline' => 'nullable|date|after:today',
                'ai_screening_enabled' => 'boolean',
                'minimum_ai_score' => 'nullable|numeric|min:0|max:10',
                'required_skills' => 'nullable|array',
                'preferred_skills' => 'nullable|array',
                'ai_criteria' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jobData = $request->all();
            $jobData['job_id'] = 'JOB' . str_pad(JobPosting::count() + 1, 6, '0', STR_PAD_LEFT);
            $jobData['status'] = 'draft';
            $jobData['created_by'] = $request->user()->name ?? 'System';
            
            // Format skills as JSON
            if (isset($jobData['required_skills'])) {
                $jobData['required_skills'] = json_encode($jobData['required_skills']);
            }
            if (isset($jobData['preferred_skills'])) {
                $jobData['preferred_skills'] = json_encode($jobData['preferred_skills']);
            }
            if (isset($jobData['ai_criteria'])) {
                $jobData['ai_criteria'] = json_encode($jobData['ai_criteria']);
            }

            $jobPosting = JobPosting::create($jobData);

            return response()->json([
                'success' => true,
                'message' => 'Job posting created successfully',
                'data' => [
                    'job_id' => $jobPosting->id,
                    'job_code' => $jobPosting->job_id,
                    'title' => $jobPosting->title,
                    'status' => $jobPosting->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Job Creation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create job posting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update job posting
     */
    public function updateJob(Request $request, int $jobId): JsonResponse
    {
        try {
            $jobPosting = JobPosting::findOrFail($jobId);
            
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'department_id' => 'sometimes|exists:departments,id',
                'position_id' => 'sometimes|exists:positions,id',
                'location' => 'nullable|string|max:255',
                'min_salary' => 'nullable|numeric|min:0',
                'max_salary' => 'nullable|numeric|min:0',
                'description' => 'sometimes|string',
                'requirements' => 'nullable|string',
                'responsibilities' => 'nullable|string',
                'benefits' => 'nullable|string',
                'type' => 'sometimes|in:full_time,part_time,contract,intern,freelance',
                'vacancies' => 'sometimes|integer|min:1',
                'application_deadline' => 'nullable|date',
                'status' => 'sometimes|in:draft,active,paused,closed,archived',
                'ai_screening_enabled' => 'boolean',
                'minimum_ai_score' => 'nullable|numeric|min:0|max:10',
                'required_skills' => 'nullable|array',
                'preferred_skills' => 'nullable|array',
                'ai_criteria' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = $request->all();
            $updateData['updated_by'] = $request->user()->name ?? 'System';
            
            // Format skills as JSON
            if (isset($updateData['required_skills'])) {
                $updateData['required_skills'] = json_encode($updateData['required_skills']);
            }
            if (isset($updateData['preferred_skills'])) {
                $updateData['preferred_skills'] = json_encode($updateData['preferred_skills']);
            }
            if (isset($updateData['ai_criteria'])) {
                $updateData['ai_criteria'] = json_encode($updateData['ai_criteria']);
            }

            $jobPosting->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Job posting updated successfully',
                'data' => [
                    'job_id' => $jobPosting->id,
                    'title' => $jobPosting->title,
                    'status' => $jobPosting->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Job Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update job posting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job details
     */
    public function getJobDetails(int $jobId): JsonResponse
    {
        try {
            $jobPosting = JobPosting::with(['department', 'position'])
                ->findOrFail($jobId);
            
            $applications = JobApplication::where('job_posting_id', $jobId)->count();
            $screenedApplications = JobApplication::where('job_posting_id', $jobId)
                ->whereNotNull('ai_score')
                ->count();
            $hiredApplications = JobApplication::where('job_posting_id', $jobId)
                ->where('status', 'hired')
                ->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'job_details' => [
                        'id' => $jobPosting->id,
                        'job_id' => $jobPosting->job_id,
                        'title' => $jobPosting->title,
                        'department' => $jobPosting->department->name ?? 'Unknown',
                        'position' => $jobPosting->position->title ?? 'Unknown',
                        'location' => $jobPosting->location,
                        'type' => ucfirst(str_replace('_', ' ', $jobPosting->type)),
                        'description' => $jobPosting->description,
                        'requirements' => $jobPosting->requirements,
                        'responsibilities' => $jobPosting->responsibilities,
                        'benefits' => $jobPosting->benefits,
                        'salary_range' => $this->formatSalaryRange($jobPosting->min_salary, $jobPosting->max_salary),
                        'vacancies' => $jobPosting->vacancies,
                        'filled_positions' => $jobPosting->filled_positions,
                        'status' => $jobPosting->status,
                        'application_deadline' => $jobPosting->application_deadline?->format('Y-m-d'),
                        'start_date' => $jobPosting->start_date?->format('Y-m-d'),
                        'remote_allowed' => $jobPosting->remote_allowed,
                        'hybrid_allowed' => $jobPosting->hybrid_allowed,
                        'urgent_hiring' => $jobPosting->urgent_hiring,
                        'featured' => $jobPosting->featured
                    ],
                    'ai_settings' => [
                        'ai_screening_enabled' => $jobPosting->ai_screening_enabled,
                        'minimum_ai_score' => $jobPosting->minimum_ai_score,
                        'required_skills' => json_decode($jobPosting->required_skills ?? '[]', true) ?? [],
                        'preferred_skills' => json_decode($jobPosting->preferred_skills ?? '[]', true) ?? [],
                        'ai_criteria' => json_decode($jobPosting->ai_criteria ?? '[]', true) ?? []
                    ],
                    'statistics' => [
                        'total_applications' => $applications,
                        'screened_applications' => $screenedApplications,
                        'hired_applications' => $hiredApplications,
                        'completion_rate' => $applications > 0 ? round(($hiredApplications / $applications) * 100, 1) : 0
                    ],
                    'created_at' => $jobPosting->created_at->format('M j, Y'),
                    'updated_at' => $jobPosting->updated_at->format('M j, Y')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Job Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load job details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job applications for a specific job
     */
    public function getJobApplications(int $jobId, Request $request): JsonResponse
    {
        try {
            $jobPosting = JobPosting::findOrFail($jobId);
            
            $applications = JobApplication::with(['candidate'])
                ->where('job_posting_id', $jobId)
                ->when($request->status && $request->status !== 'all', function($query) use ($request) {
                    return $query->where('status', $request->status);
                })
                ->when($request->search, function($query) use ($request) {
                    return $query->whereHas('candidate', function($q) use ($request) {
                        $q->where('first_name', 'like', '%' . $request->search . '%')
                          ->orWhere('last_name', 'like', '%' . $request->search . '%')
                          ->orWhere('email', 'like', '%' . $request->search . '%');
                    });
                })
                ->orderBy('applied_at', 'desc')
                ->paginate(20);
            
            $applications->getCollection()->transform(function ($application) {
                return [
                    'id' => $application->id,
                    'application_id' => $application->application_id,
                    'candidate_name' => $application->candidate->first_name . ' ' . $application->candidate->last_name,
                    'candidate_email' => $application->candidate->email,
                    'status' => $application->status,
                    'ai_score' => $application->ai_score,
                    'applied_at' => $application->applied_at->format('M j, Y'),
                    'expected_salary' => $application->expected_salary ? '₦' . number_format($application->expected_salary) : null,
                    'earliest_start_date' => $application->earliest_start_date?->format('M j, Y'),
                    'ai_recommended' => $application->ai_recommended
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'job_title' => $jobPosting->title,
                    'applications' => $applications,
                    'filters' => [
                        'status' => $request->status ?? 'all',
                        'search' => $request->search ?? ''
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Job Applications Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load job applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format salary range for display
     */
    private function formatSalaryRange($minSalary, $maxSalary): string
    {
        if (!$minSalary && !$maxSalary) {
            return 'Not specified';
        }
        
        if ($minSalary && $maxSalary) {
            return '₦' . number_format($minSalary / 1000, 0) . 'K - ₦' . number_format($maxSalary / 1000, 0) . 'K';
        }
        
        if ($minSalary) {
            return '₦' . number_format($minSalary / 1000, 0) . 'K+';
        }
        
        if ($maxSalary) {
            return 'Up to ₦' . number_format($maxSalary / 1000, 0) . 'K';
        }
        
        return 'Not specified';
    }

    /**
     * Get available actions for a job
     */
    private function getJobActions(JobPosting $job): array
    {
        $actions = ['view_applications', 'edit_form', 'preview'];
        
        if ($job->status === 'draft') {
            $actions[] = 'publish';
        } elseif ($job->status === 'active') {
            $actions[] = 'pause';
            $actions[] = 'close';
        } elseif ($job->status === 'paused') {
            $actions[] = 'resume';
            $actions[] = 'close';
        }
        
        return $actions;
    }
}
