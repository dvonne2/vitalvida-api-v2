<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function getPortalData(Request $request, $portal)
    {
        return response()->json([
            'message' => 'Portal data endpoint ready',
            'portal' => $portal,
            'data' => [
                'name' => ucfirst($portal),
                'status' => 'active',
                'timestamp' => now()
            ]
        ]);
    }
}
