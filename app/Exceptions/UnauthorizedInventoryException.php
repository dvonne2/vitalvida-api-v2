<?php

namespace App\Exceptions;

class UnauthorizedInventoryException extends \Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'Unauthorized inventory operation',
            'message' => $this->getMessage()
        ], 403);
    }
}
