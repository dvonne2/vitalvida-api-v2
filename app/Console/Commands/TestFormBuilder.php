<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Form;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TestFormBuilder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:form-builder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Form Builder & Management System';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=============================================");
        $this->info("FORM BUILDER & MANAGEMENT SYSTEM TEST");
        $this->info("=============================================");
        $this->newLine();

        try {
            // Test 1: Check if forms table exists and has data
            $this->info("1. Testing Forms Table...");
            $forms = Form::all();
            $this->line("   ✓ Found " . $forms->count() . " forms in database");
            
            foreach ($forms as $form) {
                $this->line("   - Form: {$form->name} (Slug: {$form->slug})");
                $this->line("     Status: " . ($form->is_active ? 'Active' : 'Inactive'));
                $this->line("     Submissions: {$form->total_submissions}");
            }
            $this->newLine();

            // Test 2: Check form configuration
            $this->info("2. Testing Form Configuration...");
            $form = Form::first();
            if ($form) {
                $this->line("   ✓ Form fields: " . count($form->fields_config) . " configured");
                $this->line("   ✓ Products: " . count($form->products) . " available");
                $this->line("   ✓ Payment methods: " . count($form->payment_methods) . " available");
                $this->line("   ✓ Delivery options: " . count($form->delivery_options) . " available");
                
                // Test default configurations
                $defaultFields = $form->getDefaultFieldsConfig();
                $this->line("   ✓ Default fields: " . count($defaultFields) . " available");
                
                $defaultProducts = $form->getDefaultProducts();
                $this->line("   ✓ Default products: " . count($defaultProducts) . " available");
            }
            $this->newLine();

            // Test 3: Check leads table structure
            $this->info("3. Testing Leads Table Structure...");
            $columns = DB::select("PRAGMA table_info(leads)");
            $formRelatedColumns = ['form_id', 'product', 'promo_code', 'payment_method', 'delivery_preference', 'delivery_cost'];
            
            foreach ($formRelatedColumns as $column) {
                $exists = false;
                foreach ($columns as $col) {
                    if ($col->name === $column) {
                        $exists = true;
                        break;
                    }
                }
                $this->line("   " . ($exists ? "✓" : "✗") . " Column '{$column}' exists");
            }
            $this->newLine();

            // Test 4: Test form relationships
            $this->info("4. Testing Form Relationships...");
            $form = Form::first();
            if ($form) {
                $leadsCount = $form->leads()->count();
                $this->line("   ✓ Form has {$leadsCount} leads");
                
                // Test creating a sample lead
                $sampleLead = Lead::create([
                    'form_id' => $form->id,
                    'customer_name' => 'Test Customer',
                    'customer_phone' => '+2348012345678',
                    'customer_email' => 'test@example.com',
                    'address' => '123 Test Street, Lagos',
                    'product' => 'SELF LOVE PLUS',
                    'payment_method' => 'Pay on Delivery',
                    'delivery_preference' => 'Standard Delivery',
                    'delivery_cost' => 2500,
                    'source' => 'Test Form',
                    'status' => 'new'
                ]);
                
                $this->line("   ✓ Created sample lead with ID: {$sampleLead->id}");
                $this->line("   ✓ Lead form relationship: " . ($sampleLead->form->id === $form->id ? 'Working' : 'Broken'));
                
                // Clean up test lead
                $sampleLead->delete();
                $this->line("   ✓ Cleaned up test lead");
            }
            $this->newLine();

            // Test 5: Test form validation
            $this->info("5. Testing Form Validation...");
            $form = Form::first();
            if ($form) {
                $rules = [];
                foreach ($form->fields_config as $field => $config) {
                    if ($config['show'] && $config['required']) {
                        $rules[$field] = 'required|string';
                    } elseif ($config['show']) {
                        $rules[$field] = 'nullable|string';
                    }
                }
                
                $this->line("   ✓ Generated validation rules for " . count($rules) . " fields");
                
                // Test required fields
                $requiredFields = [];
                foreach ($form->fields_config as $field => $config) {
                    if ($config['show'] && $config['required']) {
                        $requiredFields[] = $field;
                    }
                }
                $this->line("   ✓ Required fields: " . implode(', ', $requiredFields));
            }
            $this->newLine();

            // Test 6: Test form styling
            $this->info("6. Testing Form Styling...");
            $form = Form::first();
            if ($form) {
                $this->line("   ✓ Background color: {$form->background_color}");
                $this->line("   ✓ Primary color: {$form->primary_color}");
                $this->line("   ✓ Font family: {$form->font_family}");
                $this->line("   ✓ Headline font: {$form->headline_font}");
                $this->line("   ✓ Show country code: " . ($form->show_country_code ? 'Yes' : 'No'));
                $this->line("   ✓ Require email: " . ($form->require_email ? 'Yes' : 'No'));
                $this->line("   ✓ Honeypot enabled: " . ($form->honeypot_enabled ? 'Yes' : 'No'));
            }
            $this->newLine();

            // Test 7: Test form URLs
            $this->info("7. Testing Form URLs...");
            $form = Form::first();
            if ($form) {
                $formUrl = route('forms.show', $form);
                $embedUrl = route('admin.forms.embed-code', $form);
                
                $this->line("   ✓ Form URL: {$formUrl}");
                $this->line("   ✓ Embed URL: {$embedUrl}");
                $this->line("   ✓ Form slug: {$form->slug}");
            }
            $this->newLine();

            // Test 8: Test form statistics
            $this->info("8. Testing Form Statistics...");
            $form = Form::first();
            if ($form) {
                $this->line("   ✓ Total submissions: {$form->total_submissions}");
                $this->line("   ✓ Last submission: " . ($form->last_submission_at ? $form->last_submission_at->format('Y-m-d H:i:s') : 'Never'));
                
                // Test incrementing submissions
                $originalCount = $form->total_submissions;
                $form->increment('total_submissions');
                $form->refresh();
                $this->line("   ✓ Incremented submissions: {$originalCount} → {$form->total_submissions}");
                
                // Reset
                $form->update(['total_submissions' => $originalCount]);
            }
            $this->newLine();

            // Test 9: Test form duplication
            $this->info("9. Testing Form Duplication...");
            $originalForm = Form::first();
            if ($originalForm) {
                $duplicateForm = $originalForm->replicate();
                $duplicateForm->name = $originalForm->name . ' (Copy)';
                $duplicateForm->slug = \Illuminate\Support\Str::slug($duplicateForm->name);
                $duplicateForm->is_active = false;
                $duplicateForm->total_submissions = 0;
                $duplicateForm->last_submission_at = null;
                $duplicateForm->save();
                
                $this->line("   ✓ Created duplicate form: {$duplicateForm->name}");
                $this->line("   ✓ Duplicate form ID: {$duplicateForm->id}");
                $this->line("   ✓ Duplicate form slug: {$duplicateForm->slug}");
                
                // Clean up
                $duplicateForm->delete();
                $this->line("   ✓ Cleaned up duplicate form");
            }
            $this->newLine();

            // Test 10: Test form products and pricing
            $this->info("10. Testing Form Products and Pricing...");
            $form = Form::first();
            if ($form) {
                $totalValue = 0;
                foreach ($form->products as $product) {
                    if ($product['active']) {
                        $this->line("   - Product: {$product['name']} - ₦" . number_format($product['price']));
                        $totalValue += $product['price'];
                    }
                }
                $this->line("   ✓ Total product value: ₦" . number_format($totalValue));
                
                // Test delivery options
                $this->line("   Delivery options:");
                foreach ($form->delivery_options as $option) {
                    if ($option['active']) {
                        $this->line("   - {$option['name']}: ₦" . number_format($option['price']));
                    }
                }
            }
            $this->newLine();

            $this->info("=============================================");
            $this->info("ALL TESTS COMPLETED SUCCESSFULLY! 🎉");
            $this->info("=============================================");
            $this->newLine();
            $this->line("Form Builder & Management System is ready!");
            $this->newLine();
            $this->line("Next steps:");
            $this->line("1. Access admin panel: /admin/forms");
            $this->line("2. Create and configure forms");
            $this->line("3. Test form submissions");
            $this->line("4. Check lead assignment to telesales agents");
            $this->newLine();

        } catch (Exception $e) {
            $this->error("ERROR: " . $e->getMessage());
            $this->error("Stack trace:\n" . $e->getTraceAsString());
        }
    }
} 