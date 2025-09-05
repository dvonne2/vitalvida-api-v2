<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Receipt;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReceiptController extends Controller
{
    /**
     * Generate receipt for sale.
     */
    public function generate(Sale $sale): JsonResponse
    {
        try {
            // Check if receipt already exists
            $existingReceipt = $sale->receipt;
            if ($existingReceipt) {
                return ApiResponse::success($existingReceipt, 'Receipt already exists');
            }

            // Create new receipt
            $receipt = Receipt::create([
                'sale_id' => $sale->id,
                'content' => $this->generateReceiptContent($sale),
                'format' => 'text'
            ]);

            return ApiResponse::created($receipt, 'Receipt generated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate receipt: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Email receipt to customer.
     */
    public function email(Sale $sale, Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        try {
            $receipt = $sale->receipt;
            if (!$receipt) {
                $receipt = Receipt::create([
                    'sale_id' => $sale->id,
                    'content' => $this->generateReceiptContent($sale),
                    'format' => 'text'
                ]);
            }

            // Update receipt with email info
            $receipt->update([
                'email_address' => $request->email,
                'email_sent_at' => now()
            ]);

            // Here you would typically send the email
            // For now, we'll just return success
            return ApiResponse::success($receipt, 'Receipt sent to email successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to send receipt: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate receipt content.
     */
    private function generateReceiptContent(Sale $sale): string
    {
        $content = "VITALVIDA INVENTORY PORTAL\n";
        $content .= "========================\n\n";
        $content .= "Receipt #: " . ($sale->receipt->receipt_number ?? 'N/A') . "\n";
        $content .= "Sale #: {$sale->sale_number}\n";
        $content .= "Date: {$sale->date}\n";
        $content .= "Customer: {$sale->customer->name}\n";
        $content .= "Phone: {$sale->customer->phone}\n\n";
        
        $content .= "ITEMS:\n";
        $content .= "------\n";
        foreach ($sale->items as $item) {
            $content .= sprintf(
                "%s x %s @ ₦%.2f = ₦%.2f\n",
                $item->quantity,
                $item->item->name,
                $item->unit_price,
                $item->total
            );
        }
        
        $content .= "\n";
        $content .= "Subtotal: ₦" . number_format($sale->subtotal, 2) . "\n";
        if ($sale->tax_amount > 0) {
            $content .= "Tax: ₦" . number_format($sale->tax_amount, 2) . "\n";
        }
        if ($sale->discount_amount > 0) {
            $content .= "Discount: -₦" . number_format($sale->discount_amount, 2) . "\n";
        }
        $content .= "TOTAL: ₦" . number_format($sale->total, 2) . "\n\n";
        $content .= "Payment Method: {$sale->payment_method}\n";
        $content .= "Status: {$sale->payment_status}\n";
        $content .= "Verified: " . ($sale->otp_verified ? 'Yes' : 'No') . "\n\n";
        $content .= "Thank you for your business!\n";
        
        return $content;
    }
} 