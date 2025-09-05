<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminReactController extends Controller
{
    /**
     * Show the React admin application
     */
    public function index()
    {
        return view('admin-react.app');
    }
    
    /**
     * Get initial data for React app
     */
    public function getInitialData(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'permissions' => $request->user()?->getAllPermissions() ?? [],
            'config' => [
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
            ]
        ]);
    }
} 