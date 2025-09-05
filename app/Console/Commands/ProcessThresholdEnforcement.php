<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApprovalWorkflow;
use App\Models\SalaryDeduction;
use App\Services\ThresholdValidationService;
use Illuminate\Support\Facades\Log;

class ProcessThresholdEnforcement extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'threshold:process 
                            {--timeout-rejections : Process timeout rejections for expired workflows}
                            {--overdue-deductions : Process overdue salary deductions}
                            {--stats : Show threshold enforcement statistics}
                            {--urgent : Show urgent items requiring attention}';

    /**
     * The console command description.
     */
    protected $description = 'Process threshold enforcement tasks (timeout rejections, overdue deductions)';

    protected $thresholdService;

    /**
     * Create a new command instance.
     */
    public function __construct(ThresholdValidationService $thresholdService)
    {
        parent::__construct();
        $this->thresholdService = $thresholdService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚨 Threshold Enforcement Processing');
        $this->info('==================================');

        try {
            // Handle different command options
            if ($this->option('stats')) {
                return $this->showStatistics();
            }

            if ($this->option('urgent')) {
                return $this->showUrgentItems();
            }

            if ($this->option('timeout-rejections')) {
                return $this->processTimeoutRejections();
            }

            if ($this->option('overdue-deductions')) {
                return $this->processOverdueDeductions();
            }

            // Default: process all tasks
            return $this->processAllTasks();

        } catch (\Exception $e) {
            $this->error('❌ Command failed: ' . $e->getMessage());
            Log::error('Threshold enforcement command failed', [
                'error' => $e->getMessage(),
                'options' => $this->options()
            ]);
            return 1;
        }
    }

    /**
     * Process all threshold enforcement tasks
     */
    protected function processAllTasks(): int
    {
        $this->info('🔄 Processing all threshold enforcement tasks...');

        $timeoutCount = $this->processTimeoutRejections();
        $deductionCount = $this->processOverdueDeductions();

        $this->info('✅ All tasks completed successfully!');
        $this->info("📊 Summary:");
        $this->line("  Timeout rejections processed: {$timeoutCount}");
        $this->line("  Overdue deductions processed: {$deductionCount}");

        return 0;
    }

    /**
     * Process timeout rejections for expired workflows
     */
    protected function processTimeoutRejections(): int
    {
        $this->info('⏰ Processing timeout rejections...');

        $processedCount = ApprovalWorkflow::processTimeoutRejections();

        if ($processedCount > 0) {
            $this->warn("⚠️  Processed {$processedCount} timeout rejections");
            
            Log::warning('Timeout rejections processed', [
                'count' => $processedCount
            ]);
        } else {
            $this->info('✅ No timeout rejections to process');
        }

        return $processedCount;
    }

    /**
     * Process overdue salary deductions
     */
    protected function processOverdueDeductions(): int
    {
        $this->info('💰 Processing overdue salary deductions...');

        $processedCount = SalaryDeduction::processOverdueDeductions();

        if ($processedCount > 0) {
            $this->warn("⚠️  Processed {$processedCount} overdue deductions");
            
            Log::warning('Overdue deductions processed', [
                'count' => $processedCount
            ]);
        } else {
            $this->info('✅ No overdue deductions to process');
        }

        return $processedCount;
    }

    /**
     * Show threshold enforcement statistics
     */
    protected function showStatistics(): int
    {
        $this->info('📊 Threshold Enforcement Statistics');

        $stats = $this->thresholdService->getThresholdStatistics();
        $workflowStats = ApprovalWorkflow::getWorkflowStatistics();
        $deductionStats = SalaryDeduction::getDeductionStatistics();

        $this->info('Violations:');
        $this->line("  Total: {$stats['total_violations']}");
        $this->line("  Pending: {$stats['pending_approvals']}");
        $this->line("  Approved: {$stats['approved']}");
        $this->line("  Rejected: {$stats['rejected']}");
        $this->line("  Unauthorized: {$stats['unauthorized_payments']}");
        $this->line("  Compliance Rate: {$stats['compliance_rate']}%");

        $this->info('Workflows:');
        $this->line("  Total: {$workflowStats['total_workflows']}");
        $this->line("  Pending: {$workflowStats['pending_workflows']}");
        $this->line("  Approved: {$workflowStats['approved_workflows']}");
        $this->line("  Rejected: {$workflowStats['rejected_workflows']}");
        $this->line("  Expired: {$workflowStats['expired_workflows']}");
        $this->line("  Approval Rate: {$workflowStats['approval_rate']}%");

        $this->info('Salary Deductions:');
        $this->line("  Total: {$deductionStats['total_deductions']}");
        $this->line("  Pending: {$deductionStats['pending_deductions']}");
        $this->line("  Processed: {$deductionStats['processed_deductions']}");
        $this->line("  Overdue: {$deductionStats['overdue_deductions']}");
        $this->line("  Total Amount: ₦" . number_format($deductionStats['total_amount'], 2));
        $this->line("  Success Rate: {$deductionStats['success_rate']}%");

        return 0;
    }

    /**
     * Show urgent items requiring attention
     */
    protected function showUrgentItems(): int
    {
        $this->info('🚨 Urgent Items Requiring Attention');

        $urgentViolations = ThresholdViolation::getUrgentViolations();
        $urgentWorkflows = ApprovalWorkflow::getUrgentWorkflows();
        $overdueDeductions = SalaryDeduction::getOverdueDeductions();

        if (empty($urgentViolations) && empty($urgentWorkflows) && empty($overdueDeductions)) {
            $this->info('✅ No urgent items found!');
            return 0;
        }

        if (!empty($urgentViolations)) {
            $this->warn("⚠️  {$urgentViolations->count()} urgent violations:");
            foreach ($urgentViolations as $violation) {
                $this->line("  - ID: {$violation['id']}, Amount: ₦{$violation['amount']}, Category: {$violation['category']}");
            }
        }

        if (!empty($urgentWorkflows)) {
            $this->warn("⏰ {$urgentWorkflows->count()} workflows expiring soon:");
            foreach ($urgentWorkflows as $workflow) {
                $this->line("  - ID: {$workflow['id']}, Type: {$workflow['workflow_type']}, Expires: {$workflow['expires_at']}");
            }
        }

        if (!empty($overdueDeductions)) {
            $this->warn("💰 {$overdueDeductions->count()} overdue salary deductions:");
            foreach ($overdueDeductions as $deduction) {
                $this->line("  - ID: {$deduction['id']}, User: {$deduction['user']['name']}, Amount: ₦{$deduction['amount']}");
            }
        }

        return 0;
    }
} 