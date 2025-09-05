<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class FormBuilderController extends Controller
{
    /**
     * Get form configuration for a job
     */
    public function getFormConfiguration(int $jobId): JsonResponse
    {
        try {
            $jobPosting = JobPosting::with(['department', 'position'])->findOrFail($jobId);
            
            $formConfig = $this->buildFormConfiguration($jobPosting);
            $aiConfig = $this->buildAIScreeningConfig($jobPosting);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'form_configuration' => $formConfig,
                    'ai_screening_config' => $aiConfig,
                    'form_preview' => $this->generateFormPreview($formConfig),
                    'completion_analytics' => $this->getFormCompletionAnalytics($jobId)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Form Configuration Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load form configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update form configuration
     */
    public function updateFormConfiguration(Request $request, int $jobId): JsonResponse
    {
        try {
            $jobPosting = JobPosting::findOrFail($jobId);
            
            $validator = Validator::make($request->all(), [
                'fields' => 'required|array',
                'fields.*.type' => 'required|in:text,email,phone,select,textarea,file,checkbox_group,radio_group,date,number',
                'fields.*.label' => 'required|string|max:255',
                'fields.*.required' => 'boolean',
                'fields.*.validation' => 'nullable|string',
                'fields.*.options' => 'nullable|array',
                'fields.*.accepted_formats' => 'nullable|array',
                'fields.*.max_size' => 'nullable|string',
                'fields.*.max_length' => 'nullable|integer',
                'ai_screening_config' => 'nullable|array',
                'ai_screening_config.auto_screen' => 'boolean',
                'ai_screening_config.scoring_criteria' => 'nullable|array',
                'ai_screening_config.minimum_pass_score' => 'nullable|numeric|min:0|max:10'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update form configuration
            $formConfig = $request->input('fields');
            $aiConfig = $request->input('ai_screening_config');
            
            // Store form configuration in job posting
            $jobPosting->update([
                'form_configuration' => json_encode($formConfig),
                'ai_screening_config' => json_encode($aiConfig)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Form configuration updated successfully',
                'data' => [
                    'job_id' => $jobPosting->id,
                    'fields_count' => count($formConfig),
                    'ai_screening_enabled' => $aiConfig['auto_screen'] ?? false
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Update Form Configuration Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update form configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get form templates
     */
    public function getFormTemplates(): JsonResponse
    {
        try {
            $templates = [
                'standard' => [
                    'name' => 'Standard Application',
                    'description' => 'Basic application form with essential fields',
                    'fields' => $this->getStandardFields(),
                    'estimated_completion_time' => '5-10 minutes'
                ],
                'technical' => [
                    'name' => 'Technical Role Application',
                    'description' => 'Comprehensive form for technical positions',
                    'fields' => $this->getTechnicalFields(),
                    'estimated_completion_time' => '10-15 minutes'
                ],
                'executive' => [
                    'name' => 'Executive Application',
                    'description' => 'Detailed form for senior and executive roles',
                    'fields' => $this->getExecutiveFields(),
                    'estimated_completion_time' => '15-20 minutes'
                ],
                'entry_level' => [
                    'name' => 'Entry Level Application',
                    'description' => 'Simple form for entry-level positions',
                    'fields' => $this->getEntryLevelFields(),
                    'estimated_completion_time' => '3-5 minutes'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'custom_fields' => $this->getCustomFieldOptions()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Form Templates Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load form templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get form analytics
     */
    public function getFormAnalytics(int $jobId): JsonResponse
    {
        try {
            $jobPosting = JobPosting::findOrFail($jobId);
            $applications = JobApplication::where('job_posting_id', $jobId)->get();
            
            $analytics = [
                'total_applications' => $applications->count(),
                'completed_applications' => $applications->where('status', '!=', 'applied')->count(),
                'abandoned_applications' => $applications->where('status', 'applied')->count(),
                'completion_rate' => $applications->count() > 0 ? round((($applications->count() - $applications->where('status', 'applied')->count()) / $applications->count()) * 100, 1) : 0,
                'average_completion_time' => $this->calculateAverageCompletionTime($applications),
                'field_performance' => $this->analyzeFieldPerformance($jobId),
                'drop_off_points' => $this->identifyDropOffPoints($applications),
                'optimization_suggestions' => $this->generateOptimizationSuggestions($jobId)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Form Analytics Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load form analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build form configuration for a job
     */
    private function buildFormConfiguration(JobPosting $jobPosting): array
    {
        $existingConfig = json_decode($jobPosting->form_configuration ?? '[]', true);
        
        if (!empty($existingConfig)) {
            return [
                'job_id' => $jobPosting->id,
                'fields' => $existingConfig
            ];
        }

        // Generate default configuration based on job type
        $defaultFields = $this->getDefaultFields($jobPosting);
        
        return [
            'job_id' => $jobPosting->id,
            'fields' => $defaultFields
        ];
    }

    /**
     * Build AI screening configuration
     */
    private function buildAIScreeningConfig(JobPosting $jobPosting): array
    {
        $existingConfig = json_decode($jobPosting->ai_screening_config ?? '[]', true);
        
        if (!empty($existingConfig)) {
            return $existingConfig;
        }

        // Default AI configuration
        return [
            'auto_screen' => true,
            'scoring_criteria' => [
                'technical_skills' => 40,
                'experience_match' => 30,
                'cultural_fit' => 20,
                'communication' => 10
            ],
            'minimum_pass_score' => 7.0,
            'auto_rejection_criteria' => [
                'missing_required_skills' => true,
                'location_mismatch' => true,
                'experience_gap' => true
            ]
        ];
    }

    /**
     * Get default fields based on job type
     */
    private function getDefaultFields(JobPosting $jobPosting): array
    {
        $baseFields = [
            [
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'validation' => 'required|string|max:255',
                'placeholder' => 'Enter your full name'
            ],
            [
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'validation' => 'required|email',
                'placeholder' => 'Enter your email address'
            ],
            [
                'type' => 'phone',
                'label' => 'Phone Number',
                'required' => true,
                'validation' => 'required|string|max:20',
                'placeholder' => 'Enter your phone number'
            ],
            [
                'type' => 'file',
                'label' => 'Resume/CV',
                'required' => true,
                'accepted_formats' => ['pdf', 'doc', 'docx'],
                'max_size' => '5MB',
                'validation' => 'required|file|mimes:pdf,doc,docx|max:5120'
            ],
            [
                'type' => 'select',
                'label' => 'Years of Experience',
                'required' => true,
                'options' => ['0-1', '1-3', '3-5', '5-10', '10+'],
                'validation' => 'required|in:0-1,1-3,3-5,5-10,10+'
            ],
            [
                'type' => 'textarea',
                'label' => 'Why are you interested in this role?',
                'required' => true,
                'max_length' => 500,
                'validation' => 'required|string|max:500',
                'placeholder' => 'Tell us why you\'re interested in this position...'
            ]
        ];

        // Add technical fields for technical roles
        if (in_array($jobPosting->position->level ?? '', ['mid', 'senior', 'lead'])) {
            $baseFields[] = [
                'type' => 'file',
                'label' => 'Portfolio',
                'required' => false,
                'accepted_formats' => ['pdf', 'zip', 'url'],
                'max_size' => '10MB',
                'validation' => 'nullable|file|mimes:pdf,zip|max:10240'
            ];
            
            $baseFields[] = [
                'type' => 'checkbox_group',
                'label' => 'Technical Skills',
                'required' => true,
                'options' => ['React', 'TypeScript', 'Node.js', 'Next.js', 'GraphQL', 'AWS', 'Docker', 'Kubernetes'],
                'validation' => 'required|array|min:1'
            ];
        }

        // Add executive fields for executive roles
        if (in_array($jobPosting->position->level ?? '', ['manager', 'director', 'executive'])) {
            $baseFields[] = [
                'type' => 'textarea',
                'label' => 'Leadership Experience',
                'required' => true,
                'max_length' => 300,
                'validation' => 'required|string|max:300',
                'placeholder' => 'Describe your leadership experience...'
            ];
            
            $baseFields[] = [
                'type' => 'number',
                'label' => 'Team Size Managed',
                'required' => false,
                'validation' => 'nullable|integer|min:0',
                'placeholder' => 'Number of people you\'ve managed'
            ];
        }

        return $baseFields;
    }

    /**
     * Generate form preview
     */
    private function generateFormPreview(array $formConfig): array
    {
        return [
            'total_fields' => count($formConfig['fields']),
            'required_fields' => count(array_filter($formConfig['fields'], fn($field) => $field['required'] ?? false)),
            'estimated_completion_time' => $this->estimateCompletionTime($formConfig['fields']),
            'field_types' => array_count_values(array_column($formConfig['fields'], 'type')),
            'validation_rules' => array_filter(array_column($formConfig['fields'], 'validation'))
        ];
    }

    /**
     * Get form completion analytics
     */
    private function getFormCompletionAnalytics(int $jobId): array
    {
        $applications = JobApplication::where('job_posting_id', $jobId)->get();
        
        return [
            'total_applications' => $applications->count(),
            'completed_applications' => $applications->where('status', '!=', 'applied')->count(),
            'abandoned_applications' => $applications->where('status', 'applied')->count(),
            'completion_rate' => $applications->count() > 0 ? round((($applications->count() - $applications->where('status', 'applied')->count()) / $applications->count()) * 100, 1) : 0,
            'average_completion_time' => $this->calculateAverageCompletionTime($applications)
        ];
    }

    /**
     * Get standard form fields
     */
    private function getStandardFields(): array
    {
        return [
            [
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'validation' => 'required|string|max:255'
            ],
            [
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'validation' => 'required|email'
            ],
            [
                'type' => 'phone',
                'label' => 'Phone Number',
                'required' => true,
                'validation' => 'required|string|max:20'
            ],
            [
                'type' => 'file',
                'label' => 'Resume/CV',
                'required' => true,
                'accepted_formats' => ['pdf', 'doc', 'docx'],
                'max_size' => '5MB'
            ],
            [
                'type' => 'select',
                'label' => 'Years of Experience',
                'required' => true,
                'options' => ['0-1', '1-3', '3-5', '5-10', '10+']
            ],
            [
                'type' => 'textarea',
                'label' => 'Why are you interested in this role?',
                'required' => true,
                'max_length' => 500
            ]
        ];
    }

    /**
     * Get technical form fields
     */
    private function getTechnicalFields(): array
    {
        $fields = $this->getStandardFields();
        
        $fields[] = [
            'type' => 'file',
            'label' => 'Portfolio',
            'required' => false,
            'accepted_formats' => ['pdf', 'zip', 'url'],
            'max_size' => '10MB'
        ];
        
        $fields[] = [
            'type' => 'checkbox_group',
            'label' => 'Technical Skills',
            'required' => true,
            'options' => ['React', 'TypeScript', 'Node.js', 'Next.js', 'GraphQL', 'AWS', 'Docker', 'Kubernetes']
        ];
        
        $fields[] = [
            'type' => 'textarea',
            'label' => 'Technical Projects',
            'required' => false,
            'max_length' => 300
        ];
        
        return $fields;
    }

    /**
     * Get executive form fields
     */
    private function getExecutiveFields(): array
    {
        $fields = $this->getStandardFields();
        
        $fields[] = [
            'type' => 'textarea',
            'label' => 'Leadership Experience',
            'required' => true,
            'max_length' => 300
        ];
        
        $fields[] = [
            'type' => 'number',
            'label' => 'Team Size Managed',
            'required' => false,
            'validation' => 'nullable|integer|min:0'
        ];
        
        $fields[] = [
            'type' => 'textarea',
            'label' => 'Strategic Achievements',
            'required' => true,
            'max_length' => 400
        ];
        
        $fields[] = [
            'type' => 'file',
            'label' => 'References',
            'required' => false,
            'accepted_formats' => ['pdf'],
            'max_size' => '2MB'
        ];
        
        return $fields;
    }

    /**
     * Get entry level form fields
     */
    private function getEntryLevelFields(): array
    {
        return [
            [
                'type' => 'text',
                'label' => 'Full Name',
                'required' => true,
                'validation' => 'required|string|max:255'
            ],
            [
                'type' => 'email',
                'label' => 'Email Address',
                'required' => true,
                'validation' => 'required|email'
            ],
            [
                'type' => 'phone',
                'label' => 'Phone Number',
                'required' => true,
                'validation' => 'required|string|max:20'
            ],
            [
                'type' => 'file',
                'label' => 'Resume/CV',
                'required' => true,
                'accepted_formats' => ['pdf', 'doc', 'docx'],
                'max_size' => '5MB'
            ],
            [
                'type' => 'textarea',
                'label' => 'Why are you interested in this role?',
                'required' => true,
                'max_length' => 300
            ]
        ];
    }

    /**
     * Get custom field options
     */
    private function getCustomFieldOptions(): array
    {
        return [
            'field_types' => [
                'text' => 'Text Input',
                'email' => 'Email Input',
                'phone' => 'Phone Input',
                'select' => 'Dropdown Select',
                'textarea' => 'Text Area',
                'file' => 'File Upload',
                'checkbox_group' => 'Multiple Choice (Checkboxes)',
                'radio_group' => 'Single Choice (Radio Buttons)',
                'date' => 'Date Picker',
                'number' => 'Number Input'
            ],
            'validation_rules' => [
                'required' => 'Required Field',
                'email' => 'Valid Email',
                'string' => 'Text Only',
                'numeric' => 'Numbers Only',
                'min' => 'Minimum Value',
                'max' => 'Maximum Value',
                'in' => 'Must be one of specified values',
                'file' => 'File Upload',
                'mimes' => 'File Type Restriction',
                'max_size' => 'Maximum File Size'
            ]
        ];
    }

    /**
     * Estimate completion time based on fields
     */
    private function estimateCompletionTime(array $fields): string
    {
        $totalFields = count($fields);
        $fileFields = count(array_filter($fields, fn($field) => $field['type'] === 'file'));
        $textareaFields = count(array_filter($fields, fn($field) => $field['type'] === 'textarea'));
        
        $estimatedMinutes = $totalFields * 0.5 + $fileFields * 2 + $textareaFields * 1.5;
        
        if ($estimatedMinutes <= 5) return '3-5 minutes';
        if ($estimatedMinutes <= 10) return '5-10 minutes';
        if ($estimatedMinutes <= 15) return '10-15 minutes';
        return '15-20 minutes';
    }

    /**
     * Calculate average completion time
     */
    private function calculateAverageCompletionTime($applications): string
    {
        // Simulated calculation
        $avgTime = $applications->count() > 0 ? rand(5, 15) : 0;
        return $avgTime . ' minutes';
    }

    /**
     * Analyze field performance
     */
    private function analyzeFieldPerformance(int $jobId): array
    {
        // Simulated field performance analysis
        return [
            'portfolio_upload' => [
                'completion_rate' => '77%',
                'abandonment_rate' => '23%',
                'avg_time' => '2.5 minutes'
            ],
            'years_of_experience' => [
                'completion_rate' => '98%',
                'abandonment_rate' => '4%',
                'avg_time' => '0.5 minutes'
            ],
            'cover_letter' => [
                'completion_rate' => '85%',
                'abandonment_rate' => '15%',
                'avg_time' => '3.2 minutes'
            ]
        ];
    }

    /**
     * Identify drop-off points
     */
    private function identifyDropOffPoints($applications): array
    {
        // Simulated drop-off analysis
        return [
            'portfolio_upload' => '23% abandon at upload step',
            'cover_letter' => '15% abandon at text input',
            'technical_skills' => '8% abandon at checkbox selection'
        ];
    }

    /**
     * Generate optimization suggestions
     */
    private function generateOptimizationSuggestions(int $jobId): array
    {
        return [
            'Make portfolio upload optional to reduce drop-off',
            'Add progress indicator to improve completion rate',
            'Simplify technical skills selection',
            'Add auto-save functionality for long forms'
        ];
    }
}
