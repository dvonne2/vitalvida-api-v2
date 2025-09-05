<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobilePushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobilePushNotificationController extends Controller
{
    private MobilePushNotificationService $notificationService;

    public function __construct(MobilePushNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Register device token for push notifications
     */
    public function registerDevice(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'platform' => 'required|in:android,ios',
                'device_info' => 'nullable|array'
            ]);

            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            $result = $this->notificationService->registerDeviceToken(
                $user->id,
                $request->token,
                $request->platform,
                $request->device_info ?? []
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Device registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unregister device token
     */
    public function unregisterDevice(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string'
            ]);

            $result = $this->notificationService->unregisterDeviceToken($request->token);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Device unregistration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            $stats = $this->notificationService->getNotificationStats();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Statistics retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }
} 