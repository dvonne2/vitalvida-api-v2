<?php

namespace App\Exceptions;

class InsufficientStockException extends \Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'Insufficient stock',
            'message' => $this->getMessage()
        ], 400);
    }
}
