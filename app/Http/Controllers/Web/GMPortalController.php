<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FraudDetectionController;
use App\Http\Controllers\Api\MultiStateController;
use App\Http\Controllers\Api\CommunicationController;
use Illuminate\Http\Request;

class GMPortalController extends Controller
{
    public function dashboard()
    {
        // Fetch data from API controllers for demonstration
        $dashboardController = new DashboardController();
        $dashboardData = $dashboardController->index()->getData();

        return view('gm-portal.dashboard', [
            'metrics' => $dashboardData->data ?? null,
            'alerts' => $dashboardData->critical_alerts ?? [],
        ]);
    }

    public function fraudDetection()
    {
        $fraudController = new FraudDetectionController();
        $fraudData = $fraudController->index()->getData();

        return view('gm-portal.fraud-detection', [
            'fraudData' => $fraudData->data ?? null,
        ]);
    }

    public function multiState()
    {
        $multiStateController = new MultiStateController();
        $stateData = $multiStateController->index()->getData();

        return view('gm-portal.multi-state', [
            'stateData' => $stateData->data ?? null,
        ]);
    }

    public function communication()
    {
        $commController = new CommunicationController(app(\App\Services\EBulkSMSService::class));
        $commData = $commController->index()->getData();

        return view('gm-portal.communication', [
            'commData' => $commData->data ?? null,
        ]);
    }
}
