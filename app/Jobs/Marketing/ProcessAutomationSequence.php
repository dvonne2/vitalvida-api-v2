<?php

namespace App\Jobs\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Marketing\MarketingAutomationSequence;
use App\Models\Marketing\MarketingAutomationExecution;
use App\Models\Marketing\MarketingContentLibrary;
use App\Models\Customer;
use App\Services\Marketing\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessAutomationSequence implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sequence;
    protected $customer;
    protected $executionId;

    public function __construct(MarketingAutomationSequence $sequence, Customer $customer, $executionId = null)
    {
        $this->sequence = $sequence;
        $this->customer = $customer;
        $this->executionId = $executionId;
    }

    public function handle()
    {
        try {
            // Create or get existing execution record
            $execution = $this->executionId ? 
                MarketingAutomationExecution::find($this->executionId) :
                $this->createExecution();

            if (!$execution) {
                throw new \Exception('Failed to create or find automation execution');
            }

            Log::info("Processing automation sequence step", [
                'sequence_id' => $this->sequence->id,
                'customer_id' => $this->customer->id,
                'current_step' => $execution->current_step,
                'execution_id' => $execution->id
            ]);

            // Process current step
            $this->processCurrentStep($execution);

            // Check if sequence is complete
            if ($execution->current_step >= count($this->sequence->steps)) {
                $execution->update([
                    'status' => 'completed',
                    'completed_at' => Carbon::now()
                ]);

                Log::info("Automation sequence completed", [
                    'sequence_id' => $this->sequence->id,
                    'customer_id' => $this->customer->id,
                    'execution_id' => $execution->id
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Failed to process automation sequence", [
                'sequence_id' => $this->sequence->id,
                'customer_id' => $this->customer->id,
                'error' => $e->getMessage()
            ]);

            if (isset($execution)) {
                $execution->update([
                    'status' => 'failed',
                    'failed_at' => Carbon::now(),
                    'failure_reason' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }

    private function createExecution()
    {
        return MarketingAutomationExecution::create([
            'sequence_id' => $this->sequence->id,
            'customer_id' => $this->customer->id,
            'current_step' => 0,
            'status' => 'active',
            'started_at' => Carbon::now(),
            'execution_data' => [],
            'company_id' => $this->sequence->company_id
        ]);
    }

    private function processCurrentStep($execution)
    {
        $steps = $this->sequence->steps;
        $currentStepIndex = $execution->current_step;

        if ($currentStepIndex >= count($steps)) {
            return; // Sequence complete
        }

        $step = $steps[$currentStepIndex];
        $stepType = $step['type'];

        switch ($stepType) {
            case 'delay':
                $this->processDelayStep($execution, $step);
                break;
            case 'whatsapp':
                $this->processWhatsAppStep($execution, $step);
                break;
            case 'email':
                $this->processEmailStep($execution, $step);
                break;
            case 'sms':
                $this->processSMSStep($execution, $step);
                break;
            default:
                Log::warning("Unknown step type in automation sequence", [
                    'step_type' => $stepType,
                    'sequence_id' => $this->sequence->id
                ]);
        }
    }

    private function processDelayStep($execution, $step)
    {
        $delayHours = $step['delay_hours'] ?? 1;
        $nextRunTime = Carbon::now()->addHours($delayHours);

        // Schedule next step
        ProcessAutomationSequence::dispatch($this->sequence, $this->customer, $execution->id)
            ->delay($nextRunTime);

        // Update execution to next step
        $execution->update([
            'current_step' => $execution->current_step + 1,
            'execution_data' => array_merge($execution->execution_data ?? [], [
                'last_delay_step' => [
                    'delay_hours' => $delayHours,
                    'scheduled_for' => $nextRunTime->toISOString()
                ]
            ])
        ]);

        Log::info("Delay step processed, next step scheduled", [
            'execution_id' => $execution->id,
            'delay_hours' => $delayHours,
            'next_run_time' => $nextRunTime
        ]);
    }

    private function processWhatsAppStep($execution, $step)
    {
        $contentId = $step['content_id'] ?? null;
        $channels = $step['channels'] ?? ['whatsapp'];

        if (!in_array('whatsapp', $channels)) {
            $this->moveToNextStep($execution);
            return;
        }

        if (!$this->customer->phone) {
            Log::warning("Customer has no phone number for WhatsApp step", [
                'customer_id' => $this->customer->id,
                'execution_id' => $execution->id
            ]);
            $this->moveToNextStep($execution);
            return;
        }

        // Get content from library
        $content = $contentId ? MarketingContentLibrary::find($contentId) : null;
        $message = $content ? $content->content : ($step['message'] ?? 'Hello from VitalVida!');

        // Personalize message
        $message = $this->personalizeMessage($message, $this->customer);

        // Send WhatsApp message
        $whatsappService = new WhatsAppService($this->sequence->company_id);
        $result = $whatsappService->sendMessage(
            $this->customer->phone,
            $message,
            $step['template_name'] ?? null,
            $step['template_params'] ?? []
        );

        // Update execution
        $execution->update([
            'current_step' => $execution->current_step + 1,
            'execution_data' => array_merge($execution->execution_data ?? [], [
                'whatsapp_steps' => array_merge(
                    $execution->execution_data['whatsapp_steps'] ?? [],
                    [[
                        'step_index' => $execution->current_step,
                        'sent_at' => Carbon::now()->toISOString(),
                        'success' => $result['success'],
                        'provider' => $result['provider'] ?? null,
                        'message' => $message
                    ]]
                )
            ])
        ]);

        // Schedule next step immediately if successful
        if ($result['success']) {
            ProcessAutomationSequence::dispatch($this->sequence, $this->customer, $execution->id);
        }
    }

    private function processEmailStep($execution, $step)
    {
        // Similar to WhatsApp but for email
        // Implementation would depend on your email service
        $this->moveToNextStep($execution);
    }

    private function processSMSStep($execution, $step)
    {
        // Similar to WhatsApp but for SMS
        // Implementation would depend on your SMS service
        $this->moveToNextStep($execution);
    }

    private function moveToNextStep($execution)
    {
        $execution->update([
            'current_step' => $execution->current_step + 1
        ]);

        // Schedule next step processing
        ProcessAutomationSequence::dispatch($this->sequence, $this->customer, $execution->id);
    }

    private function personalizeMessage($message, $customer)
    {
        $replacements = [
            '{customer_name}' => $customer->name ?? 'Valued Customer',
            '{customer_first_name}' => explode(' ', $customer->name ?? '')[0] ?? 'Friend',
            '{customer_email}' => $customer->email ?? '',
            '{customer_phone}' => $customer->phone ?? '',
            '{company_name}' => 'VitalVida'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
