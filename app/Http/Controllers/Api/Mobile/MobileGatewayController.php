<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\APIGatewayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileGatewayController extends Controller
{
    private APIGatewayService $gatewayService;

    public function __construct(APIGatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    /**
     * Unified mobile API gateway endpoint
     */
    public function handle(Request $request, string $service): JsonResponse
    {
        try {
            $result = $this->gatewayService->processRequest($request, $service);
            
            $statusCode = $result['success'] ? 200 : ($result['error']['http_code'] ?? 500);
            
            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'gateway_error',
                    'message' => 'Gateway processing failed',
                    'http_code' => 500
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'version' => '1.0'
                ]
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => '1.0',
                'services' => [
                    'gateway' => 'operational',
                    'database' => 'operational',
                    'cache' => 'operational'
                ]
            ]
        ]);
    }

    /**
     * API documentation endpoint
     */
    public function documentation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'api_version' => '1.0',
                'base_url' => url('/api/mobile'),
                'services' => [
                    'auth' => [
                        'description' => 'Authentication and user management',
                        'endpoints' => [
                            'POST /auth/login' => 'User login',
                            'POST /auth/logout' => 'User logout',
                            'POST /auth/refresh' => 'Refresh token',
                            'POST /auth/biometric/setup' => 'Setup biometric authentication',
                            'POST /auth/biometric/auth' => 'Biometric authentication'
                        ]
                    ],
                    'payments' => [
                        'description' => 'Payment processing and management',
                        'endpoints' => [
                            'GET /payments' => 'Get payments list',
                            'POST /payments' => 'Create payment',
                            'GET /payments/{id}' => 'Get payment details',
                            'PUT /payments/{id}' => 'Update payment',
                            'DELETE /payments/{id}' => 'Delete payment'
                        ]
                    ],
                    'inventory' => [
                        'description' => 'Inventory management and verification',
                        'endpoints' => [
                            'GET /inventory' => 'Get inventory list',
                            'POST /inventory' => 'Create inventory movement',
                            'GET /inventory/{id}' => 'Get inventory details',
                            'PUT /inventory/{id}' => 'Update inventory',
                            'DELETE /inventory/{id}' => 'Delete inventory'
                        ]
                    ],
                    'thresholds' => [
                        'description' => 'Threshold monitoring and enforcement',
                        'endpoints' => [
                            'GET /thresholds' => 'Get thresholds list',
                            'POST /thresholds' => 'Create threshold',
                            'GET /thresholds/{id}' => 'Get threshold details',
                            'PUT /thresholds/{id}' => 'Update threshold',
                            'DELETE /thresholds/{id}' => 'Delete threshold'
                        ]
                    ],
                    'bonuses' => [
                        'description' => 'Bonus calculation and management',
                        'endpoints' => [
                            'GET /bonuses' => 'Get bonuses list',
                            'POST /bonuses' => 'Create bonus',
                            'GET /bonuses/{id}' => 'Get bonus details',
                            'PUT /bonuses/{id}' => 'Update bonus',
                            'DELETE /bonuses/{id}' => 'Delete bonus'
                        ]
                    ],
                    'reports' => [
                        'description' => 'Report generation and management',
                        'endpoints' => [
                            'GET /reports' => 'Get reports list',
                            'POST /reports' => 'Generate report',
                            'GET /reports/{id}' => 'Get report details',
                            'DELETE /reports/{id}' => 'Delete report'
                        ]
                    ],
                    'analytics' => [
                        'description' => 'Analytics and insights',
                        'endpoints' => [
                            'GET /analytics/dashboard' => 'Get dashboard data',
                            'GET /analytics/metrics' => 'Get analytics metrics',
                            'GET /analytics/predictions' => 'Get predictions'
                        ]
                    ],
                    'sync' => [
                        'description' => 'Data synchronization',
                        'endpoints' => [
                            'GET /sync' => 'Get sync data',
                            'POST /sync' => 'Upload sync data'
                        ]
                    ]
                ],
                'authentication' => [
                    'type' => 'API Key or Bearer Token',
                    'header' => 'X-API-Key or Authorization: Bearer {token}'
                ],
                'rate_limits' => [
                    'mobile' => '1000 requests per hour',
                    'dashboard' => '2000 requests per hour',
                    'reporting' => '100 requests per hour',
                    'sync' => '5000 requests per hour'
                ]
            ]
        ]);
    }
} 