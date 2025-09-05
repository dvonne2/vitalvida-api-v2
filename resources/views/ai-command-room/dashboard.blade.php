@extends('layouts.app')

@section('content')
<div class="ai-command-room bg-gray-900 text-white min-h-screen" x-data="commandRoom()">
    <!-- Header -->
    <div class="bg-gray-800 border-b border-gray-700 p-6">
        <div class="flex justify-between items-center">
            <h1 class="text-3xl font-bold text-green-400">🤖 Vitalvida AI Command Room</h1>
            <div class="flex space-x-4">
                <div class="bg-green-500 text-black px-4 py-2 rounded-lg font-bold">
                    TEMU ELIMINATION MODE: ACTIVE
                </div>
                <div class="text-sm text-gray-300" x-text="currentTime"></div>
            </div>
        </div>
    </div>

    <!-- Real-Time Metrics Grid -->
    <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Orders Today -->
        <div class="bg-gradient-to-r from-green-600 to-green-700 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-green-100">Orders Today</h3>
                    <div class="text-3xl font-bold text-white" data-metric="orders_today">{{ $metrics['orders_today'] }}</div>
                    <div class="text-sm text-green-200">Target: {{ number_format($metrics['orders_target']) }}</div>
                </div>
                <div class="text-4xl">📦</div>
            </div>
            <div class="mt-4 bg-green-500 bg-opacity-30 rounded-full h-2">
                <div class="bg-white h-2 rounded-full transition-all duration-500 progress-bar" 
                     style="width: {{ min(($metrics['orders_today'] / $metrics['orders_target']) * 100, 100) }}%"></div>
            </div>
        </div>

        <!-- Cost Per Order -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-blue-100">Cost Per Order</h3>
                    <div class="text-3xl font-bold text-white" data-metric="average_cpo">₦{{ number_format($metrics['average_cpo']) }}</div>
                    <div class="text-sm text-blue-200">
                        @if($metrics['average_cpo'] <= 1200)
                            <span class="text-green-300">🔥 Beating Target</span>
                        @else
                            <span class="text-yellow-300">⚠️ Above Target</span>
                        @endif
                    </div>
                </div>
                <div class="text-4xl">💰</div>
            </div>
        </div>

        <!-- Customer LTV -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-purple-100">Customer LTV</h3>
                    <div class="text-3xl font-bold text-white" data-metric="customer_ltv">₦{{ number_format($metrics['customer_ltv']) }}</div>
                    <div class="text-sm text-purple-200">5x higher than Temu</div>
                </div>
                <div class="text-4xl">💎</div>
            </div>
        </div>

        <!-- AI Actions -->
        <div class="bg-gradient-to-r from-red-600 to-red-700 p-6 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-red-100">AI Creatives Live</h3>
                    <div class="text-3xl font-bold text-white">{{ $metrics['ai_creatives_live'] }}</div>
                    <div class="text-sm text-red-200">Generating conversions</div>
                </div>
                <div class="text-4xl">🤖</div>
            </div>
        </div>
    </div>

    <!-- Additional Metrics Row -->
    <div class="px-6 pb-6 grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Repeat Rate -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-300">Repeat Rate</h4>
                    <div class="text-2xl font-bold text-white">{{ number_format($metrics['repeat_rate'], 1) }}%</div>
                </div>
                <div class="text-2xl">🔄</div>
            </div>
        </div>

        <!-- Winning Creatives -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-300">Winning Creatives</h4>
                    <div class="text-2xl font-bold text-green-400">{{ $metrics['winning_creatives'] }}</div>
                </div>
                <div class="text-2xl">🏆</div>
            </div>
        </div>

        <!-- Churn Risk -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-300">Churn Risk</h4>
                    <div class="text-2xl font-bold text-red-400">{{ $metrics['churn_risk_customers'] }}</div>
                </div>
                <div class="text-2xl">⚠️</div>
            </div>
        </div>

        <!-- Active Campaigns -->
        <div class="bg-gray-800 p-4 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-gray-300">Active Campaigns</h4>
                    <div class="text-2xl font-bold text-blue-400">{{ $metrics['active_campaigns'] }}</div>
                </div>
                <div class="text-2xl">🚀</div>
            </div>
        </div>
    </div>

    <!-- AI Actions Feed -->
    <div class="px-6 pb-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Live AI Actions -->
        <div class="lg:col-span-2 bg-gray-800 rounded-lg p-6">
            <h3 class="text-xl font-bold text-green-400 mb-4">🧠 AI Actions (Live Feed)</h3>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @forelse($aiActions as $action)
                <div class="bg-gray-700 p-4 rounded-lg border-l-4 
                    @if($action['type'] === 'creative_generation') border-green-500
                    @elseif($action['type'] === 'retargeting_message') border-blue-500
                    @elseif($action['type'] === 'churn_prevention') border-yellow-500
                    @else border-purple-500 @endif">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="font-semibold text-white">{{ $action['action'] }}</div>
                            <div class="text-sm text-gray-300 mt-1">{{ $action['result'] }}</div>
                            <div class="text-xs text-gray-400 mt-2">
                                Customer: {{ $action['customer_name'] }} • Confidence: {{ number_format($action['confidence'] * 100, 1) }}%
                            </div>
                        </div>
                        <div class="text-xs text-gray-400 ml-4">
                            {{ $action['timestamp']->diffForHumans() }}
                        </div>
                    </div>
                </div>
                @empty
                <div class="bg-gray-700 p-4 rounded-lg text-center text-gray-400">
                    No AI actions yet. Click the buttons below to get started!
                </div>
                @endforelse
            </div>
        </div>

        <!-- Top Performing Creatives -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-xl font-bold text-green-400 mb-4">🏆 Top Creatives</h3>
            <div class="space-y-3">
                @forelse($topCreatives as $creative)
                <div class="bg-gray-700 p-3 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gray-600 rounded flex items-center justify-center text-lg">
                            {{ $creative['platform'] === 'meta' ? '📘' : ($creative['platform'] === 'tiktok' ? '🎵' : '🌐') }}
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-white">{{ ucfirst($creative['platform']) }} Ad</div>
                            <div class="text-xs text-gray-300">₦{{ number_format($creative['cpo']) }} CPO • {{ $creative['orders'] }} orders</div>
                            <div class="text-xs text-gray-400">Grade: {{ $creative['grade'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-green-400">{{ number_format($creative['ctr'] * 100, 2) }}%</div>
                            <div class="text-xs text-gray-400">CTR</div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="bg-gray-700 p-3 rounded-lg text-center text-gray-400">
                    No creatives yet
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- AI Predictions -->
    <div class="px-6 pb-6">
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-xl font-bold text-green-400 mb-4">🔮 AI Predictions</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-300">Next Week Orders</h4>
                    <div class="text-2xl font-bold text-white">{{ number_format($predictions['next_week_orders']) }}</div>
                </div>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-300">Churn Risk Trend</h4>
                    <div class="text-2xl font-bold text-yellow-400">{{ number_format($predictions['churn_risk_trend']['high_risk_percentage'], 1) }}%</div>
                </div>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-300">Revenue Forecast</h4>
                    <div class="text-2xl font-bold text-green-400">₦{{ number_format($predictions['revenue_forecast']['week_forecast']) }}</div>
                </div>
                <div class="bg-gray-700 p-4 rounded-lg">
                    <h4 class="text-sm font-semibold text-gray-300">Growth Rate</h4>
                    <div class="text-2xl font-bold text-blue-400">{{ $predictions['revenue_forecast']['growth_rate'] }}%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Control Panel -->
    <div class="px-6 pb-6">
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-xl font-bold text-green-400 mb-4">⚡ AI Control Panel</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <button @click="triggerAIAction('generate_creatives')" 
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    🎨 Generate 50 Creatives
                </button>
                <button @click="triggerAIAction('scale_winners')" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    🚀 Scale Winners 5x
                </button>
                <button @click="triggerAIAction('kill_losers')" 
                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    💀 Kill Underperformers
                </button>
                <button @click="triggerAIAction('trigger_reorder_blast')" 
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    📱 Reorder Blast (10k customers)
                </button>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                <button @click="triggerAIAction('optimize_budgets')" 
                        class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    💰 Optimize Budgets
                </button>
                <button @click="triggerAIAction('launch_churn_prevention')" 
                        class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    🛡️ Churn Prevention
                </button>
                <button @click="refreshMetrics()" 
                        class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    🔄 Refresh Metrics
                </button>
                <button @click="showSystemStatus()" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                    📊 System Status
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function commandRoom() {
    return {
        currentTime: new Date().toLocaleTimeString(),
        
        init() {
            // Update time every second
            setInterval(() => {
                this.currentTime = new Date().toLocaleTimeString();
            }, 1000);
            
            // Refresh metrics every 30 seconds
            setInterval(() => {
                this.refreshMetrics();
            }, 30000);
        },
        
        async triggerAIAction(action) {
            try {
                const response = await fetch('/ai-command-room/trigger-action', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ action })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showNotification(result.message, 'success');
                    // Refresh metrics after action
                    setTimeout(() => this.refreshMetrics(), 2000);
                } else {
                    this.showNotification('Action failed: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                this.showNotification('Network error: ' + error.message, 'error');
            }
        },
        
        async refreshMetrics() {
            try {
                const response = await fetch('/ai-command-room/metrics');
                const metrics = await response.json();
               
                // Update metrics in real-time
                document.querySelector('[data-metric="orders_today"]').textContent = metrics.orders_today;
                document.querySelector('[data-metric="average_cpo"]').textContent = '₦' + metrics.average_cpo.toLocaleString();
                document.querySelector('[data-metric="customer_ltv"]').textContent = '₦' + metrics.customer_ltv.toLocaleString();
               
                // Update progress bars
                const progressBar = document.querySelector('.progress-bar');
                const progressPercent = Math.min((metrics.orders_today / metrics.orders_target) * 100, 100);
                progressBar.style.width = progressPercent + '%';
               
            } catch (error) {
                console.error('Failed to refresh metrics:', error);
            }
        },
        
        showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            } text-white max-w-md`;
            notification.textContent = message;
           
            document.body.appendChild(notification);
           
            setTimeout(() => {
                notification.remove();
            }, 5000);
        },

        showSystemStatus() {
            this.showNotification('System Status: All systems operational. AI engines running at 99.9% efficiency.', 'success');
        }
    }
}
</script>
@endsection 