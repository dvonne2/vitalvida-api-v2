<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\DADistanceMatrix;
use App\Models\TransferRecommendation;
use App\Models\Bin;
use Illuminate\Http\Request;
use App\Models\StockVelocityLog; // Added this import

class GeographicController extends Controller
{
    // GET /api/geographic/distance-matrix
    public function getDistanceMatrix(Request $request)
    {
        $distances = DADistanceMatrix::with(['fromDA', 'toDA'])
            ->orderBy('distance_km')
            ->get()
            ->map(function($distance) {
                return [
                    'from' => $distance->fromDA->da_code,
                    'to' => $distance->toDA->da_code,
                    'distance_km' => $distance->distance_km,
                    'travel_time' => $distance->travel_time_minutes,
                    'cost' => $distance->transport_cost,
                    'route_quality' => $distance->route_quality,
                    'cost_per_km' => $distance->transport_cost_per_km
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $distances
        ]);
    }
    
    // POST /api/geographic/generate-transfers
    public function generateTransferRecommendations(Request $request)
    {
        $recommendations = $this->analyzeOptimalTransfers();
        
        return response()->json([
            'status' => 'success',
            'data' => $recommendations,
            'summary' => [
                'total_recommendations' => count($recommendations),
                'critical' => collect($recommendations)->where('priority', 'critical')->count(),
                'potential_savings' => collect($recommendations)->sum('potential_savings')
            ]
        ]);
    }
    
    // GET /api/geographic/regional-heatmap
    public function getRegionalHeatmap(Request $request)
    {
        $heatmapData = DeliveryAgent::with('zobin')
            ->where('status', 'active')
            ->get()
            ->map(function($da) {
                $bin = Bin::where('delivery_agent_id', $da->id)->first();
                
                return [
                    'da_code' => $da->da_code,
                    'location' => [
                        'state' => $da->state,
                        'city' => $da->city,
                        'coordinates' => $this->getCoordinates($da->state, $da->city)
                    ],
                    'stock_level' => $bin->current_stock_count ?? 0,
                    'velocity' => $this->calculateVelocity($da->id),
                    'heat_score' => $this->calculateHeatScore($da->id)
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $heatmapData,
            'zones' => $this->getZonePerformance()
        ]);
    }
    
    private function analyzeOptimalTransfers()
    {
        $recommendations = [];
        $agents = DeliveryAgent::with('user')->where('status', 'active')->get();
        
        foreach ($agents as $fromAgent) {
            $fromBin = Bin::where('delivery_agent_id', $fromAgent->id)->first();
            if (!$fromBin || ($fromBin->current_stock_count ?? 0) <= 10) continue;
            
            foreach ($agents as $toAgent) {
                if ($fromAgent->id === $toAgent->id) continue;
                
                $toBin = Bin::where('delivery_agent_id', $toAgent->id)->first();
                if (!$toBin || ($toBin->current_stock_count ?? 0) >= 5) continue;
                
                $distance = DADistanceMatrix::where('from_da_id', $fromAgent->id)
                    ->where('to_da_id', $toAgent->id)
                    ->first();
                    
                if (!$distance || $distance->distance_km > 100) continue;
                
                $transferQty = min(10, ($fromBin->current_stock_count ?? 0) - 5);
                $savings = $this->calculateTransferSavings($fromAgent->id, $toAgent->id, $transferQty);
                
                if ($savings > 1000) {
                    $recommendations[] = TransferRecommendation::create([
                        'from_da_id' => $fromAgent->id,
                        'to_da_id' => $toAgent->id,
                        'recommended_quantity' => $transferQty,
                        'priority' => $savings > 5000 ? 'critical' : 'high',
                        'potential_savings' => $savings,
                        'reasoning' => "Transfer {$transferQty} units from {$fromAgent->da_code} to {$toAgent->da_code} - potential savings: ₦{$savings}",
                        'logistics_data' => [
                            'distance_km' => $distance->distance_km,
                            'transport_cost' => $distance->transport_cost,
                            'estimated_time' => $distance->travel_time_minutes
                        ],
                        'recommended_at' => now()
                    ]);
                }
            }
        }
        
        return $recommendations;
    }
    
    private function calculateVelocity($daId)
    {
        // Simplified velocity calculation
        return rand(1, 10) / 10;
    }
    
    private function calculateHeatScore($daId)
    {
        // Simplified heat score (0-100)
        return rand(10, 100);
    }
    
    private function getCoordinates($state, $city)
    {
        // Simplified coordinates - in production, use actual geocoding
        $coordinates = [
            'Lagos' => ['lat' => 6.5244, 'lng' => 3.3792],
            'Abuja' => ['lat' => 9.0765, 'lng' => 7.3986],
            'Kano' => ['lat' => 12.0022, 'lng' => 8.5920],
            'Port Harcourt' => ['lat' => 4.8156, 'lng' => 7.0498]
        ];
        
        return $coordinates[$city] ?? ['lat' => 0, 'lng' => 0];
    }
    
    private function calculateTransferSavings($fromId, $toId, $quantity)
    {
        // Simplified savings calculation
        return $quantity * rand(100, 800);
    }
    
    private function getZonePerformance()
    {
        return [
            'SW' => ['performance' => 85, 'color' => 'green'],
            'SE' => ['performance' => 72, 'color' => 'yellow'],
            'NC' => ['performance' => 90, 'color' => 'green'],
            'NE' => ['performance' => 45, 'color' => 'red'],
            'NW' => ['performance' => 68, 'color' => 'orange'],
            'SS' => ['performance' => 78, 'color' => 'yellow']
        ];
    }

    // GET /api/geographic/optimize-routes
    public function optimizeRoutes(Request $request)
    {
        $recommendations = TransferRecommendation::with(['fromDA', 'toDA'])
            ->where('status', 'pending')
            ->orderBy('priority', 'desc')
            ->orderBy('potential_savings', 'desc')
            ->get();

        $optimizedRoutes = [];
        foreach ($recommendations as $rec) {
            $route = $this->calculateOptimalRoute($rec);
            if ($route) {
                $optimizedRoutes[] = $route;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $optimizedRoutes,
            'summary' => [
                'total_routes' => count($optimizedRoutes),
                'total_savings' => collect($optimizedRoutes)->sum('potential_savings'),
                'total_distance' => collect($optimizedRoutes)->sum('distance_km')
            ]
        ]);
    }

    // GET /api/geographic/velocity-analysis
    public function getVelocityAnalysis(Request $request)
    {
        $velocityData = StockVelocityLog::with('deliveryAgent')
            ->whereBetween('tracking_date', [
                now()->subDays(30), 
                now()
            ])
            ->get()
            ->groupBy('delivery_agent_id')
            ->map(function($logs, $daId) {
                $avgVelocity = $logs->avg('daily_velocity');
                $da = $logs->first()->deliveryAgent;
                
                return [
                    'da_code' => $da->da_code,
                    'location' => $da->state . ', ' . $da->city,
                    'avg_velocity' => round($avgVelocity, 2),
                    'velocity_grade' => $this->getVelocityGrade($avgVelocity),
                    'stockout_days' => $logs->sum('stockout_days'),
                    'opportunity_cost' => $logs->sum('opportunity_cost'),
                    'trend' => $this->calculateTrend($logs)
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $velocityData,
            'analytics' => [
                'highest_velocity' => $velocityData->max('avg_velocity'),
                'lowest_velocity' => $velocityData->min('avg_velocity'),
                'average_velocity' => $velocityData->avg('avg_velocity'),
                'total_opportunity_cost' => $velocityData->sum('opportunity_cost')
            ]
        ]);
    }

    // POST /api/geographic/emergency-redistribution
    public function emergencyRedistribution(Request $request)
    {
        $request->validate([
            'urgent_da_ids' => 'required|array',
            'max_distance' => 'integer|min:1|max:500'
        ]);

        $urgentDAs = $request->urgent_da_ids;
        $maxDistance = $request->max_distance ?? 100;
        
        $emergencyTransfers = [];
        
        foreach ($urgentDAs as $urgentDAId) {
            $nearbyDAs = DADistanceMatrix::where('to_da_id', $urgentDAId)
                ->where('distance_km', '<=', $maxDistance)
                ->orderBy('distance_km')
                ->with('fromDA')
                ->get();

            foreach ($nearbyDAs as $nearby) {
                $fromBin = Bin::where('delivery_agent_id', $nearby->from_da_id)->first();
                if ($fromBin && $fromBin->current_stock_count > 5) {
                    $emergencyTransfers[] = [
                        'from_da' => $nearby->fromDA->da_code,
                        'to_da_id' => $urgentDAId,
                        'available_stock' => $fromBin->current_stock_count,
                        'distance_km' => $nearby->distance_km,
                        'transport_cost' => $nearby->transport_cost,
                        'estimated_time' => $nearby->travel_time_minutes,
                        'priority' => 'emergency'
                    ];
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'emergency_transfers' => $emergencyTransfers,
            'summary' => [
                'total_options' => count($emergencyTransfers),
                'fastest_option' => collect($emergencyTransfers)->sortBy('estimated_time')->first(),
                'cheapest_option' => collect($emergencyTransfers)->sortBy('transport_cost')->first()
            ]
        ]);
    }

    // HELPER METHODS
    private function calculateOptimalRoute($recommendation)
    {
        $distance = DADistanceMatrix::where('from_da_id', $recommendation->from_da_id)
            ->where('to_da_id', $recommendation->to_da_id)
            ->first();

        if (!$distance) return null;

        return [
            'recommendation_id' => $recommendation->id,
            'from_da' => $recommendation->fromDA->da_code,
            'to_da' => $recommendation->toDA->da_code,
            'quantity' => $recommendation->recommended_quantity,
            'distance_km' => $distance->distance_km,
            'transport_cost' => $distance->transport_cost,
            'estimated_time' => $distance->travel_time_minutes,
            'potential_savings' => $recommendation->potential_savings,
            'efficiency_score' => $recommendation->efficiency_score,
            'route_quality' => $distance->route_quality
        ];
    }

    private function getVelocityGrade($velocity)
    {
        if ($velocity >= 8) return 'Excellent';
        if ($velocity >= 5) return 'Good';
        if ($velocity >= 3) return 'Average';
        if ($velocity >= 1) return 'Poor';
        return 'Critical';
    }

    private function calculateTrend($logs)
    {
        $recent = $logs->sortByDesc('tracking_date')->take(7)->avg('daily_velocity');
        $previous = $logs->sortByDesc('tracking_date')->skip(7)->take(7)->avg('daily_velocity');
        
        if ($recent > $previous) return 'increasing';
        if ($recent < $previous) return 'decreasing';
        return 'stable';
    }
}
