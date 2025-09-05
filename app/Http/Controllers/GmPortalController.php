<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GmPortalController extends Controller
{
    protected $apiBaseUrl;

    public function __construct()
    {
        $this->apiBaseUrl = config('app.url') . '/api/gm-portal';
    }

    /**
     * Show GM Portal dashboard
     */
    public function dashboard()
    {
        return view('gm-portal.dashboard');
    }

    /**
     * Show fraud detection page
     */
    public function fraudDetection()
    {
        return view('gm-portal.fraud-detection');
    }

    /**
     * Show financial intelligence page
     */
    public function financialIntelligence()
    {
        return view('gm-portal.financial-intelligence');
    }

    /**
     * Show multi-state operations page
     */
    public function multiState()
    {
        return view('gm-portal.multi-state');
    }

    /**
     * Show predictive analytics page
     */
    public function predictiveAnalytics()
    {
        return view('gm-portal.predictive-analytics');
    }

    /**
     * Show communication hub page
     */
    public function communication()
    {
        return view('gm-portal.communication');
    }
}
