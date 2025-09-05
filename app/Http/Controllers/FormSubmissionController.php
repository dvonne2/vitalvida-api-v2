<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Lead;
use App\Jobs\AssignLeadJob;
use Illuminate\Http\Request;

class FormSubmissionController extends Controller
{
    public function show(Form $form)
    {
        if (!$form->is_active) {
            abort(404);
        }

        return view('forms.show', compact('form'));
    }

    public function store(Request $request, Form $form)
    {
        // Honeypot check
        if ($form->honeypot_enabled && $request->filled('website')) {
            return redirect()->back();
        }

        // Build validation rules dynamically based on form config
        $rules = $this->buildValidationRules($form);
        $validated = $request->validate($rules);

        // Calculate delivery cost
        $deliveryCost = $this->calculateDeliveryCost($form, $validated['delivery_preference']);

        // Create lead
        $lead = Lead::create([
            'form_id' => $form->id,
            'customer_name' => $validated['name'],
            'customer_phone' => $this->formatPhoneNumber($request),
            'customer_email' => $validated['email'] ?? null,
            'address' => $validated['address'],
            'product' => $validated['product'],
            'promo_code' => $validated['promo_code'] ?? null,
            'payment_method' => $validated['payment_method'],
            'delivery_preference' => $validated['delivery_preference'],
            'delivery_cost' => $deliveryCost,
            'source' => $validated['source'] ?? 'Website Form',
            'status' => 'new'
        ]);

        // Update form statistics
        $form->increment('total_submissions');
        $form->update(['last_submission_at' => now()]);

        // Trigger AI Sales Manager assignment
        AssignLeadJob::dispatch($lead);

        // Send webhook if configured
        if ($form->webhook_url) {
            // Dispatch webhook job here
        }

        return redirect()->back()->with('success', $form->thank_you_message);
    }

    public function legacyStore(Request $request)
    {
        // Handle legacy form submissions for backward compatibility
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'state' => 'required|string',
            'address' => 'required|string',
            'product' => 'required|string',
            'promo_code' => 'nullable|string',
            'source' => 'nullable|string'
        ]);

        $lead = Lead::create([
            'customer_name' => $validated['name'],
            'customer_phone' => $validated['phone'],
            'customer_email' => $validated['email'] ?? null,
            'address' => $validated['address'],
            'product' => $validated['product'],
            'promo_code' => $validated['promo_code'] ?? null,
            'source' => $validated['source'] ?? 'Legacy Form',
            'status' => 'new'
        ]);

        // Trigger AI Sales Manager assignment
        AssignLeadJob::dispatch($lead);

        return redirect()->back()->with('success', 'Thanks! Your order has been received. One of our team members will call you shortly to confirm.');
    }

    private function buildValidationRules(Form $form)
    {
        $rules = [];
        
        foreach ($form->fields_config as $field => $config) {
            if ($config['show'] && $config['required']) {
                $rules[$field] = 'required|string';
            } elseif ($config['show']) {
                $rules[$field] = 'nullable|string';
            }
        }

        // Special rules
        if (isset($rules['email'])) {
            $rules['email'] = str_replace('string', 'email', $rules['email']);
        }
        
        $rules['product'] = 'required|string';
        $rules['payment_method'] = 'required|string';
        $rules['delivery_preference'] = 'required|string';

        return $rules;
    }

    private function formatPhoneNumber(Request $request)
    {
        $countryCode = $request->input('country_code', '+234');
        $phone = $request->input('phone');
        return $countryCode . $phone;
    }

    private function calculateDeliveryCost(Form $form, $deliveryType)
    {
        foreach ($form->delivery_options as $option) {
            if ($option['name'] === $deliveryType) {
                return $option['price'];
            }
        }
        return 0;
    }
} 