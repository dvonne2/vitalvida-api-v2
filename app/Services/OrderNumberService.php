<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderNumberService
{
    /**
     * Generate a unique numeric order number for Moniepoint integration
     */
    public function generateOrderNumber(): string
    {
        // Start from 10001 to ensure 5-digit numbers
        $baseNumber = 10001;
        
        // Get all orders and filter numeric ones in PHP
        $numericOrders = Order::all()->filter(function ($order) {
            return is_numeric($order->order_number);
        });
        
        if ($numericOrders->count() > 0) {
            $maxNumber = $numericOrders->max(function ($order) {
                return (int) $order->order_number;
            });
            $baseNumber = $maxNumber + 1;
        }
        
        // Ensure we don't exceed reasonable limits
        if ($baseNumber > 999999) {
            $baseNumber = 10001; // Reset if we get too high
        }
        
        return (string) $baseNumber;
    }
    
    /**
     * Validate if an order number is numeric (Moniepoint-friendly)
     */
    public function isNumericOrderNumber(string $orderNumber): bool
    {
        return preg_match('/^[0-9]+$/', $orderNumber);
    }
    
    /**
     * Get the next available order number
     */
    public function getNextOrderNumber(): string
    {
        return $this->generateOrderNumber();
    }
} 