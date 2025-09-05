<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\InventoryReceiveController;
use App\Http\Controllers\Api\ItemController;
// use App\Http\Controllers\Api\NotificationController; // Commented out - controller missing
use App\Http\Controllers\Api\TelesalesController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AccountantController;
use App\Http\Controllers\Api\ManufacturingController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryTransferController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryPortal\DashboardController as InventoryPortalDashboardController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\FinancialIntelligenceController;
use App\Http\Controllers\Api\ProfitFirstController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// RBAC System Authentication Routes (Root Level)
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

// Health check endpoint
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'message' => 'VitalVida API is running',
        'timestamp' => now()
    ]);
});

// V1 API Routes for React Frontend Connection
Route::prefix('v1')->group(function () {
    // Test endpoint
    Route::get('/test', function() {
        return response()->json([
            'message' => 'VitalVida API working successfully',
            'timestamp' => now(),
            'status' => 'connected'
        ]);
    });
    
    // Auth endpoints
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Compatibility Auth routes expected by frontend: /api/v1/auth/*
    Route::prefix('auth')->group(function () {
        // POST /api/v1/auth/login
        Route::post('/login', [AuthController::class, 'login']);
        // GET /api/v1/auth/me
        Route::get('/me', [AuthController::class, 'user'])->middleware('auth:sanctum');
        // POST /api/v1/auth/logout
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });
    
    // Portal endpoints (protected by auth later)
    Route::get('/portals/{portal}', [PortalController::class, 'getPortalData']);
    
    // PORTAL-SPECIFIC API ROUTES FOR REACT FRONTEND
    
    // Telesales Portal Routes
    Route::prefix('portals/telesales')->group(function () {
        Route::get('/dashboard', [TelesalesController::class, 'performance']);
        Route::get('/performance', [TelesalesController::class, 'performance']);
        Route::get('/reps/{id}', [TelesalesController::class, 'show']);
        Route::post('/reps/{id}/block', [TelesalesController::class, 'blockRep']);
        Route::post('/reps/{id}/bonus', [TelesalesController::class, 'awardBonus']);
        Route::post('/reps/{id}/training', [TelesalesController::class, 'scheduleTraining']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/reassign', [OrderController::class, 'reassign']);
        Route::post('/orders/{id}/call', [OrderController::class, 'callCustomer']);
        Route::post('/orders/{id}/flag', [OrderController::class, 'flagOrder']);
        Route::get('/statistics', [OrderController::class, 'statistics']);
    });
    
    // Accountant Portal Routes
    Route::prefix('portals/accountant')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/journals', [JournalController::class, 'index']);
        Route::post('/journals', [JournalController::class, 'store']);
        Route::get('/journals/{id}', [JournalController::class, 'show']);
        Route::put('/journals/{id}', [JournalController::class, 'update']);
        Route::delete('/journals/{id}', [JournalController::class, 'destroy']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/{id}', [PaymentController::class, 'show']);
        Route::get('/budgets', [BudgetController::class, 'index']);
        Route::post('/budgets', [BudgetController::class, 'store']);
        Route::get('/budgets/{id}', [BudgetController::class, 'show']);
        Route::put('/budgets/{id}', [BudgetController::class, 'update']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/financial-intelligence', [FinancialIntelligenceController::class, 'index']);
        Route::get('/profit-first', [ProfitFirstController::class, 'index']);
    });
    
    // Manufacturing Portal Routes
    Route::prefix('portals/manufacturing')->group(function () {
        Route::get('/dashboard', [InventoryController::class, 'summary']);
        Route::get('/products', [ItemController::class, 'index']);
        Route::post('/products', [ItemController::class, 'store']);
        Route::get('/products/{id}', [ItemController::class, 'show']);
        Route::put('/products/{id}', [ItemController::class, 'update']);
        Route::delete('/products/{id}', [ItemController::class, 'destroy']);
        Route::get('/inventory', [InventoryController::class, 'summary']);
        Route::get('/inventory/lowStockAlerts', [InventoryController::class, 'lowStockAlerts']);
        Route::get('/inventory/stockMovement', [InventoryController::class, 'stockMovement']);
        Route::post('/inventory/receive', [InventoryReceiveController::class, 'receive']);
        Route::post('/inventory/transfer', [InventoryTransferController::class, 'store']);
        Route::get('/inventory/movements', [InventoryReceiveController::class, 'movements']);
        Route::get('/inventory/analytics', [InventoryReceiveController::class, 'analytics']);
        // Route::get('/inventory/reports', [InventoryReportController::class, 'index']); // Commented out - controller missing
        // Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store']); // Commented out - controller missing
        // Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index']); // Commented out - controller missing
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    });
    
    // Logistics Portal Routes
    Route::prefix('portals/logistics')->group(function () {
        // Route::get('/dashboard', [DeliveryAgentController::class, 'index']); // Commented out - controller missing
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/assign-da', [OrderController::class, 'assignDeliveryAgent']);
        Route::post('/orders/{id}/generate-otp', [OrderController::class, 'generateOTP']);
        Route::post('/orders/{id}/verify-delivery', [OrderController::class, 'verifyDelivery']);
        // Route::get('/delivery-agents', [DeliveryAgentController::class, 'index']); // Commented out - controller missing
        // Route::get('/delivery-agents/{id}', [DeliveryAgentController::class, 'show']); // Commented out - controller missing
        // Route::post('/delivery-agents/{id}/assign-order', [DeliveryAgentController::class, 'assignOrder']); // Commented out - controller missing
        // Route::get('/delivery-routes', [DeliveryAgentController::class, 'getRoutes']); // Commented out - controller missing
        // Route::get('/delivery-performance', [DeliveryAgentController::class, 'getPerformance']); // Commented out - controller missing
        // Route::get('/logistics-costs', [LogisticsCostController::class, 'index']); // Commented out - controller missing
        Route::get('/analytics', [AnalyticsController::class, 'index']);
    });
});

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Public test routes for development
Route::prefix('test')->group(function () {
    Route::get('/inventory/movements', [InventoryReceiveController::class, 'movements']);
    Route::get('/inventory/dashboard-stats', [InventoryReceiveController::class, 'dashboardStats']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
    
    // User Management routes
    Route::prefix('admin/users')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\UserManagementController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\UserManagementController::class, 'store']);
        Route::get('/stats', [\App\Http\Controllers\Api\UserManagementController::class, 'stats']);
        Route::get('/{id}', [\App\Http\Controllers\Api\UserManagementController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\UserManagementController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\UserManagementController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [\App\Http\Controllers\Api\UserManagementController::class, 'toggleStatus']);
    });
    
    // Unified Integration Routes - VitalVida + Role Inventory Management
    Route::prefix('unified-inventory')->group(function () {
        // Core integration endpoints
        Route::get('/dashboard', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'unifiedDashboard']);
        Route::post('/sync-da', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'syncDAData']);
        Route::post('/sync-inventory', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'syncInventoryData']);
        Route::post('/full-sync', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'fullSync']);
        Route::get('/health', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'healthCheck']);
        
        // Real-time status
        Route::get('/sync-status', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'syncStatus']);
        Route::post('/trigger-sync', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'triggerSync']);
    });

    // InventoryPortal Dashboard Routes - All UI Components
    Route::prefix('dashboard')->group(function () {
        Route::get('/overview', [InventoryPortalDashboardController::class, 'getOverview']);
        Route::get('/login-status', [InventoryPortalDashboardController::class, 'getLoginStatus']);
        Route::get('/reviews', [InventoryPortalDashboardController::class, 'getReviews']);
        Route::get('/system-actions', [InventoryPortalDashboardController::class, 'getSystemActions']);
        Route::get('/weekly-metrics', [InventoryPortalDashboardController::class, 'getWeeklyMetrics']);
    });
    
    // InventoryPortal Alert Routes
    Route::prefix('alerts')->group(function () {
        Route::get('/critical', [InventoryPortalDashboardController::class, 'getCriticalAlerts']);
        Route::get('/enforcement-tasks', [InventoryPortalDashboardController::class, 'getEnforcementTasks']);
        Route::put('/{alertId}/resolve', [InventoryPortalDashboardController::class, 'resolveAlert']);
    });
    
    // Purchase Orders
    Route::prefix('purchase-orders')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::post('/', [PurchaseOrderController::class, 'store']);
        Route::get('/{id}', [PurchaseOrderController::class, 'show']);
        Route::put('/{id}', [PurchaseOrderController::class, 'update']);
    });
    
    // Inventory
    Route::prefix('inventory')->group(function () {
        Route::post('/receive', [InventoryReceiveController::class, 'receive']);
        Route::post('/receive/upload', [InventoryReceiveController::class, 'upload']);
        Route::get('/movements', [InventoryReceiveController::class, 'movements']);
        Route::get('/dashboard-stats', [InventoryReceiveController::class, 'dashboardStats']);
        Route::get('/analytics', [InventoryReceiveController::class, 'analytics']);
    });
    
    // Items
    Route::apiResource('items', ItemController::class);
    
    // Referral API routes (protected)
    Route::prefix('referrals')->group(function () {
        Route::get('/form-preview', [ReferralController::class, 'formPreview']);
        Route::get('/summary', [ReferralController::class, 'summary']);
        Route::get('/relations', [ReferralController::class, 'relations']);
    });
    
    // Notifications
// Route::get('/notifications/fc-reminder', [NotificationController::class, 'fcReminder']); // Commented out - controller missing

// VitalVida Inventory System Routes
Route::prefix('vitalvida-inventory')->middleware('auth:sanctum')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaInventoryController::class, 'dashboard']);
    
    // Suppliers Management Routes
    Route::get('/suppliers/overview', [\App\Http\Controllers\Api\VitalVidaSuppliersController::class, 'overview']);
    Route::get('/suppliers/analytics', [\App\Http\Controllers\Api\VitalVidaSuppliersController::class, 'analytics']);
    Route::apiResource('/suppliers', \App\Http\Controllers\Api\VitalVidaSuppliersController::class);
    Route::get('/suppliers/{id}/performance', [\App\Http\Controllers\Api\VitalVidaSuppliersController::class, 'performance']);
    Route::post('/suppliers/{id}/rate', [\App\Http\Controllers\Api\VitalVidaSuppliersController::class, 'rate']);
    
    // Items Management
    Route::get('/items/summary', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaInventoryController::class, 'itemsSummary']);
    Route::get('/items', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaInventoryController::class, 'items']);
    
    // Delivery Agents
    Route::get('/delivery-agents', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'index']);
    Route::post('/delivery-agents', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'store']);
    Route::get('/delivery-agents/{id}', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'show']);
    Route::put('/delivery-agents/{id}', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'update']);
    Route::get('/delivery-agents/{id}/orders', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'orders']);
    Route::get('/delivery-agents/{id}/products', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'products']);
    Route::post('/delivery-agents/{id}/assign-products', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaDeliveryAgentController::class, 'assignProducts']);
    
    // Abdul Auditor System
    Route::prefix('abdul')->group(function () {
        Route::get('/audit-metrics', [\App\Http\Controllers\Api\VitalVidaInventory\AbdulAuditorController::class, 'auditMetrics']);
        Route::get('/flags', [\App\Http\Controllers\Api\VitalVidaInventory\AbdulAuditorController::class, 'flags']);
        Route::get('/agent-scorecard', [\App\Http\Controllers\Api\VitalVidaInventory\AbdulAuditorController::class, 'agentScorecard']);
        Route::post('/investigate', [\App\Http\Controllers\Api\VitalVidaInventory\AbdulAuditorController::class, 'investigate']);
        Route::post('/audit-agent/{id}', [\App\Http\Controllers\Api\VitalVidaInventory\AbdulAuditorController::class, 'auditAgent']);
    });
    
    // Analytics & Reports
    Route::get('/analytics/overview', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaInventoryController::class, 'analyticsOverview']);
    
    // Inventory Management
    Route::get('/inventory/overview', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaInventoryController::class, 'inventoryOverview']);
    
    // Stock Transfers
    Route::get('/stock-transfers', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaStockTransferController::class, 'index']);
    Route::post('/stock-transfers', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaStockTransferController::class, 'store']);
    Route::get('/stock-transfers/{id}', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaStockTransferController::class, 'show']);
    Route::put('/stock-transfers/{id}/status', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaStockTransferController::class, 'updateStatus']);
    
    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/company', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaSettingsController::class, 'getCompanySettings']);
        Route::put('/company', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaSettingsController::class, 'updateCompanySettings']);
        Route::get('/security', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaSettingsController::class, 'getSecuritySettings']);
        Route::put('/security', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaSettingsController::class, 'updateSecuritySettings']);
        Route::get('/system', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaSettingsController::class, 'getSystemSettings']);
        Route::put('/system', [\App\Http\Controllers\Api\VitalVidaInventory\VitalVidaSettingsController::class, 'updateSystemSettings']);
    });
});

// Close auth group opened at line 168
});

// Marketing Routes
Route::prefix('marketing')->middleware(['marketing.access', 'marketing.rate.limit'])->group(function () {
    // Marketing Dashboard
    Route::get('/dashboard/stats', [\App\Http\Controllers\API\Marketing\MarketingDashboardController::class, 'getStats']);
    
    // Marketing Content Management
    Route::post('/content/generate', [\App\Http\Controllers\API\Marketing\MarketingContentController::class, 'generateContent']);
    Route::get('/content/library', [\App\Http\Controllers\API\Marketing\MarketingContentController::class, 'index']);
    Route::post('/content/upload', [\App\Http\Controllers\API\Marketing\MarketingContentController::class, 'upload']);
    Route::get('/content/{id}/variations', [\App\Http\Controllers\API\Marketing\MarketingContentController::class, 'getVariations']);
    Route::put('/content/{id}/performance', [\App\Http\Controllers\API\Marketing\MarketingContentController::class, 'updatePerformance']);
    
    // Marketing WhatsApp Automation (3-Provider System)
    Route::post('/whatsapp/send', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'sendMessage']);
    Route::post('/whatsapp/bulk-send', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'bulkSend']);
    Route::post('/whatsapp/automate', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'automateSequence']);
    Route::get('/whatsapp/templates', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'getTemplates']);
    Route::get('/whatsapp/provider-status', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'getProviderStatus']);
    Route::post('/whatsapp/switch-provider', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'switchProvider']);
    Route::get('/whatsapp/logs', [\App\Http\Controllers\API\Marketing\MarketingWhatsAppController::class, 'getLogs']);
    
    // Marketing Campaign Management
    Route::apiResource('campaigns', \App\Http\Controllers\API\Marketing\MarketingCampaignController::class);
    Route::post('/campaigns/{id}/launch', [\App\Http\Controllers\API\Marketing\MarketingCampaignController::class, 'launch']);
    Route::get('/campaigns/{id}/performance', [\App\Http\Controllers\API\Marketing\MarketingCampaignController::class, 'getPerformance']);
    
    // Marketing Brand Management
    Route::apiResource('brands', \App\Http\Controllers\API\Marketing\MarketingBrandController::class);
    Route::post('/brands/{id}/duplicate', [\App\Http\Controllers\API\Marketing\MarketingBrandController::class, 'duplicate']);
    
    // Marketing Performance & Analytics
    Route::get('/analytics/performance', [\App\Http\Controllers\API\Marketing\MarketingAnalyticsController::class, 'getPerformance']);
    Route::get('/analytics/customer-journey', [\App\Http\Controllers\API\Marketing\MarketingAnalyticsController::class, 'getCustomerJourney']);
    Route::get('/analytics/roi', [\App\Http\Controllers\API\Marketing\MarketingAnalyticsController::class, 'getROI']);
    Route::get('/analytics/channel-performance', [\App\Http\Controllers\API\Marketing\MarketingAnalyticsController::class, 'getChannelPerformance']);
    Route::get('/analytics/whatsapp-provider-performance', [\App\Http\Controllers\API\Marketing\MarketingAnalyticsController::class, 'getWhatsAppProviderPerformance']);
    
    // Marketing Referral System
    Route::post('/referrals/create', [\App\Http\Controllers\API\Marketing\MarketingReferralController::class, 'create']);
    Route::get('/referrals/performance', [\App\Http\Controllers\API\Marketing\MarketingReferralController::class, 'getPerformance']);
    Route::get('/referrals/leaderboard', [\App\Http\Controllers\API\Marketing\MarketingReferralController::class, 'getLeaderboard']);
    
    // Marketing Automation
    Route::post('/automation/create-sequence', [\App\Http\Controllers\API\Marketing\MarketingAutomationController::class, 'createSequence']);
    Route::get('/automation/sequences', [\App\Http\Controllers\API\Marketing\MarketingAutomationController::class, 'getSequences']);
    Route::post('/automation/{id}/activate', [\App\Http\Controllers\API\Marketing\MarketingAutomationController::class, 'activate']);
    
    // Marketing Audience Management (ERP Customer Integration)
    Route::get('/audiences', [\App\Http\Controllers\API\Marketing\MarketingAudienceController::class, 'index']);
    Route::post('/audiences/create', [\App\Http\Controllers\API\Marketing\MarketingAudienceController::class, 'create']);
    Route::get('/audiences/{id}/customers', [\App\Http\Controllers\API\Marketing\MarketingAudienceController::class, 'getCustomers']);
    
    // TRUE OMNIPRESENCE MARKETING SYSTEM
    Route::prefix('omnipresence')->group(function () {
        Route::post('/analyze-customer-presence', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'analyzeCustomerPresence']);
        Route::post('/calculate-relevancy', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'calculateRelevancy']);
        Route::post('/build-intimacy', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'buildCustomerIntimacy']);
        Route::post('/deploy-trust-signals', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'deployTrustSignals']);
        Route::post('/create-unified-experience', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'createUnifiedExperience']);
        Route::get('/customer-channel-map', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'getCustomerChannelMap']);
        Route::get('/relevancy-analytics', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'getRelevancyAnalytics']);
        Route::get('/intimacy-leaderboard', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'getIntimacyLeaderboard']);
        Route::get('/unified-experience-journey', [\App\Http\Controllers\API\Marketing\OmnipresenceController::class, 'getUnifiedExperienceJourney']);
    });

    // TRUST SIGNAL MANAGEMENT SYSTEM
    Route::prefix('trust-signals')->group(function () {
        Route::post('/', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'createTrustSignal']);
        Route::get('/', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'getTrustSignals']);
        Route::put('/{id}', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'updateTrustSignal']);
        Route::delete('/{id}', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'deleteTrustSignal']);
        Route::post('/{id}/deploy', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'deployTrustSignal']);
        Route::get('/recommendations', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'getTrustSignalRecommendations']);
        Route::get('/analytics', [\App\Http\Controllers\API\Marketing\TrustSignalController::class, 'getTrustSignalAnalytics']);
    });
});

// Advanced Analytics and Automation Routes (Phase 4)
Route::prefix('advanced-analytics')->group(function () {
    // Test endpoint
    Route::get('/test', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Advanced Analytics API is working',
            'timestamp' => now()
        ]);
    });
    
    // Main dashboard
    Route::get('/dashboard', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'overview_metrics' => [
                    'total_agents' => 47,
                    'active_agents' => 42,
                    'total_products' => 156,
                    'total_inventory_value' => 15750000,
                    'average_performance' => 78.5,
                    'compliance_rate' => 87.2,
                    'automation_efficiency' => 92.1,
                    'predictive_accuracy' => 84.3
                ],
                'predictive_insights' => [
                    'stockout_risk_items' => 12,
                    'reorder_recommendations' => 8,
                    'demand_surge_predictions' => 3
                ],
                'performance_analytics' => [
                    'top_performers' => 12,
                    'needs_improvement' => 8,
                    'average_rating' => 4.2
                ],
                'risk_assessment' => [
                    'critical_risks' => 2,
                    'high_risks' => 5,
                    'medium_risks' => 12,
                    'low_risks' => 28
                ],
                'generated_at' => now()
            ]
        ]);
    });
    
    
    // Performance Analytics
    Route::get('/performance', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'agent_performance_distribution' => [
                    ['rating' => '4.5-5.0', 'count' => 12, 'percentage' => 25.5],
                    ['rating' => '4.0-4.4', 'count' => 18, 'percentage' => 38.3],
                    ['rating' => '3.5-3.9', 'count' => 11, 'percentage' => 23.4],
                    ['rating' => '3.0-3.4', 'count' => 6, 'percentage' => 12.8]
                ],
                'top_performers' => [
                    ['name' => 'Adebayo Ogundimu', 'rating' => 4.8, 'zone' => 'Lagos'],
                    ['name' => 'Fatima Abdullahi', 'rating' => 4.7, 'zone' => 'Abuja']
                ],
                'average_performance' => 78.5,
                'top_performers_count' => 12,
                'underperformers_count' => 8
            ]
        ]);
    });
    
    // Risk Assessment
    Route::get('/risk-assessment', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'critical_risks' => 2,
                'high_risks' => 5,
                'medium_risks' => 12,
                'low_risks' => 28,
                'operational_risks' => [
                    ['type' => 'stockout', 'severity' => 'high', 'product' => 'Vitamin D3'],
                    ['type' => 'compliance', 'severity' => 'medium', 'zone' => 'Port Harcourt']
                ],
                'mitigation_strategies' => [
                    ['risk' => 'stockout', 'action' => 'Emergency procurement initiated'],
                    ['risk' => 'compliance', 'action' => 'Additional training scheduled']
                ]
            ]
        ]);
    });
    
    // Optimization Recommendations
    Route::get('/optimization', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'quick_wins' => [
                    ['type' => 'route_optimization', 'impact' => 'high', 'description' => 'Optimize delivery routes in Lagos zone'],
                    ['type' => 'inventory_rebalancing', 'impact' => 'medium', 'description' => 'Rebalance stock between zones']
                ],
                'cost_savings' => [
                    ['category' => 'logistics', 'potential_savings' => 250000, 'timeframe' => '30 days'],
                    ['category' => 'inventory', 'potential_savings' => 180000, 'timeframe' => '60 days']
                ]
            ]
        ]);
    });
    
    // Predictive Analytics
    Route::get('/predictive', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'stockout_predictions' => [
                    ['product' => 'Vitamin D3', 'days_until_stockout' => 5, 'urgency' => 'high'],
                    ['product' => 'Omega 3', 'days_until_stockout' => 12, 'urgency' => 'medium']
                ],
                'demand_forecasts' => [
                    ['product' => 'Multivitamin', 'predicted_demand' => 450, 'confidence' => 87],
                    ['product' => 'Calcium', 'predicted_demand' => 320, 'confidence' => 92]
                ],
                'predictive_accuracy' => 84.3
            ]
        ]);
    });
});

// Unified Inventory Integration Routes (Phases 1-2)
Route::prefix('unified-inventory')->middleware('auth:sanctum')->group(function () {
    // Integration Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'unifiedDashboard']);
    Route::get('/sync-status', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'getSyncStatus']);
    
    // Real-time Sync Operations
    Route::post('/sync/agent/{agentId}', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'syncAgent']);
    Route::post('/sync/stock-allocation', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'syncStockAllocation']);
    Route::post('/sync/compliance-action', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'syncComplianceAction']);
    
    // Conflict Detection and Resolution
    Route::get('/conflicts', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'getConflicts']);
    Route::post('/conflicts/resolve', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'resolveConflicts']);
    Route::get('/conflicts/summary', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'getConflictSummary']);
    
    // Monitoring and Health
    Route::get('/health', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'getSystemHealth']);
    Route::post('/health/auto-recovery', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'triggerAutoRecovery']);
    
    // Analytics and Reporting
    Route::get('/analytics/integration', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'getIntegrationAnalytics']);
    Route::get('/analytics/performance', [\App\Http\Controllers\Api\VitalVidaRoleIntegrationController::class, 'getPerformanceAnalytics']);
});
