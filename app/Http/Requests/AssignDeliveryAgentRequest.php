<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\DeliveryAgent;
use Illuminate\Foundation\Http\FormRequest;

class AssignDeliveryAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_agent_id' => 'required|exists:delivery_agents,id',
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $order = Order::find($this->route('orderId'));
            $agent = DeliveryAgent::find($this->delivery_agent_id);
            
            if ($order && $agent) {
                // Check if agent has required stock
                foreach ($order->product_details as $item => $qty) {
                    if (!isset($agent->current_stock[$item]) || $agent->current_stock[$item] < $qty) {
                        $validator->errors()->add('delivery_agent_id', 
                            "Agent does not have sufficient {$item} stock (needs {$qty}, has " . 
                            ($agent->current_stock[$item] ?? 0) . ")");
                    }
                }
                
                // Check if agent is in correct location
                if (!str_contains(strtolower($agent->location), strtolower($order->customer_location))) {
                    $validator->errors()->add('delivery_agent_id', 
                        'Agent location does not match customer location');
                }
                
                // Check if agent is active
                if ($agent->status !== 'active') {
                    $validator->errors()->add('delivery_agent_id', 
                        'Selected delivery agent is not active');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'delivery_agent_id.required' => 'Please select a delivery agent.',
            'delivery_agent_id.exists' => 'Selected delivery agent does not exist.',
            'notes.max' => 'Notes cannot exceed 500 characters.'
        ];
    }
} 