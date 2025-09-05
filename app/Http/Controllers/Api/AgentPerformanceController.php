<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AgentPerformanceController extends Controller
{
    public function searchAgents(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'search' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:active,inactive,suspended,warning',
                'min_rating' => 'sometimes|numeric|min:0|max:5',
                'sort_by' => 'sometimes|in:da_code,rating,success_rate,performance_score',
                'sort_order' => 'sometimes|in:asc,desc',
                'limit' => 'sometimes|integer|min:1|max:100',
                'offset' => 'sometimes|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $query = DB::table('delivery_agents')
                ->select([
                    '*',
                    DB::raw('CASE 
                        WHEN total_deliveries > 0 
                        THEN ROUND((successful_deliveries * 100.0 / total_deliveries), 2) 
                        ELSE 0 
                    END as success_rate'),
                    DB::raw('CASE 
                        WHEN total_deliveries > 0 
                        THEN ROUND(((successful_deliveries * 100.0 / total_deliveries) * 0.6 + rating * 20), 2)
                        ELSE ROUND(rating * 20, 2)
                    END as performance_score')
                ]);

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('da_code', 'LIKE', "%{$search}%")
                      ->orWhere('vehicle_number', 'LIKE', "%{$search}%")
                      ->orWhere('current_location', 'LIKE', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('min_rating')) {
                $query->where('rating', '>=', $request->min_rating);
            }

            $sortBy = $request->get('sort_by', 'performance_score');
            $sortOrder = $request->get('sort_order', 'desc');

            switch ($sortBy) {
                case 'success_rate':
                    $query->orderBy('success_rate', $sortOrder);
                    break;
                case 'performance_score':
                    $query->orderBy('performance_score', $sortOrder);
                    break;
                case 'rating':
                    $query->orderBy('rating', $sortOrder);
                    break;
                default:
                    $query->orderBy('da_code', $sortOrder);
            }

            $limit = $request->get('limit', 20);
            $offset = $request->get('offset', 0);
            
            $totalCount = $query->count();
            $agents = $query->limit($limit)->offset($offset)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'agents' => $agents,
                    'pagination' => [
                        'total' => $totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $totalCount
                    ]
                ],
                'message' => 'Delivery agents retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Agent search error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching agents',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getAvailableAgents(Request $request)
    {
        try {
            $query = DB::table('delivery_agents')
                ->select([
                    '*',
                    DB::raw('ROUND((successful_deliveries * 100.0 / total_deliveries), 2) as success_rate'),
                    DB::raw('ROUND(((successful_deliveries * 100.0 / total_deliveries) * 0.6 + rating * 20), 2) as performance_score')
                ])
                ->where('status', 'active')
                ->where('vehicle_status', 'available')
                ->orderBy('performance_score', 'desc');

            $limit = $request->get('limit', 10);
            $agents = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'available_agents' => $agents,
                    'count' => $agents->count()
                ],
                'message' => 'Available delivery agents retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Available agents error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available agents',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getAgentLoadStatus(Request $request)
    {
        try {
            $agents = DB::table('delivery_agents')
                ->select([
                    'id', 'da_code', 'vehicle_number', 'status', 'current_location',
                    'total_deliveries', 'successful_deliveries', 'vehicle_status', 'rating',
                    DB::raw('ROUND((successful_deliveries * 100.0 / total_deliveries), 2) as success_rate'),
                    DB::raw('CASE 
                        WHEN total_deliveries = 0 THEN "available"
                        WHEN total_deliveries <= 20 THEN "light"
                        WHEN total_deliveries <= 50 THEN "moderate"
                        ELSE "heavy"
                    END as workload_status'),
                    DB::raw('ROUND(((successful_deliveries * 100.0 / total_deliveries) * 0.6 + rating * 20), 2) as performance_score')
                ])
                ->where('status', '!=', 'inactive')
                ->orderBy('performance_score', 'desc')
                ->get();

            $summary = [
                'total_agents' => $agents->count(),
                'by_status' => [
                    'active' => $agents->where('status', 'active')->count(),
                    'warning' => $agents->where('status', 'warning')->count(),
                    'suspended' => $agents->where('status', 'suspended')->count(),
                ],
                'by_workload' => [
                    'available' => $agents->where('workload_status', 'available')->count(),
                    'light_load' => $agents->where('workload_status', 'light')->count(),
                    'moderate_load' => $agents->where('workload_status', 'moderate')->count(),
                    'heavy_load' => $agents->where('workload_status', 'heavy')->count(),
                ],
                'performance_metrics' => [
                    'average_deliveries' => round($agents->avg('total_deliveries'), 2),
                    'average_success_rate' => round($agents->avg('success_rate'), 2),
                    'average_rating' => round($agents->avg('rating'), 2),
                    'average_performance_score' => round($agents->avg('performance_score'), 2),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'agents' => $agents,
                    'summary' => $summary
                ],
                'message' => 'Agent load status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Agent load status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving agent load status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getAgentRankings(Request $request)
    {
        try {
            $agents = DB::table('delivery_agents')
                ->select([
                    'id', 'da_code', 'vehicle_number', 'status', 'current_location',
                    'total_deliveries', 'successful_deliveries', 'rating', 'total_earnings', 'strikes_count',
                    DB::raw('ROUND((successful_deliveries * 100.0 / total_deliveries), 2) as success_rate'),
                    DB::raw('ROUND(((successful_deliveries * 100.0 / total_deliveries) * 0.6 + rating * 20), 2) as performance_score'),
                    DB::raw('ROW_NUMBER() OVER (ORDER BY ((successful_deliveries * 100.0 / total_deliveries) * 0.6 + rating * 20) DESC) as rank_position')
                ])
                ->where('status', '!=', 'inactive')
                ->orderBy('performance_score', 'desc')
                ->limit($request->get('limit', 20))
                ->get();

            $agents = $agents->map(function ($agent) {
                $agent->performance_badge = $this->getPerformanceBadge($agent->performance_score, $agent->success_rate);
                $agent->status_icon = $this->getStatusIcon($agent->status);
                return $agent;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'rankings' => $agents,
                    'total_ranked' => $agents->count(),
                    'algorithm_info' => [
                        'formula' => 'Performance Score = (Success Rate × 0.6) + (Rating × 20)',
                        'max_score' => 160,
                        'factors' => [
                            'success_rate' => '60% weight',
                            'customer_rating' => '40% weight'
                        ]
                    ]
                ],
                'message' => 'Agent rankings retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Agent rankings error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving agent rankings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getAgentReports(Request $request)
    {
        try {
            if ($request->has('agent_id')) {
                $agents = DB::table('delivery_agents')->where('id', $request->agent_id)->get();
            } else {
                $agents = DB::table('delivery_agents')->where('status', '!=', 'inactive')->get();
            }

            $reports = $agents->map(function ($agent) {
                $successRate = $agent->total_deliveries > 0 
                    ? round(($agent->successful_deliveries * 100.0 / $agent->total_deliveries), 2) 
                    : 0;

                $performanceScore = $agent->total_deliveries > 0 
                    ? round((($successRate * 0.6) + ($agent->rating * 20)), 2)
                    : round($agent->rating * 20, 2);

                return [
                    'agent_info' => [
                        'id' => $agent->id,
                        'da_code' => $agent->da_code,
                        'vehicle_number' => $agent->vehicle_number,
                        'status' => $agent->status,
                        'current_location' => $agent->current_location,
                    ],
                    'performance_metrics' => [
                        'total_deliveries' => $agent->total_deliveries,
                        'successful_deliveries' => $agent->successful_deliveries,
                        'failed_deliveries' => $agent->total_deliveries - $agent->successful_deliveries,
                        'success_rate' => $successRate,
                        'customer_rating' => $agent->rating,
                        'performance_score' => $performanceScore,
                    ],
                    'financial_metrics' => [
                        'total_earnings' => $agent->total_earnings,
                        'commission_rate' => $agent->commission_rate,
                        'avg_earnings_per_delivery' => $agent->total_deliveries > 0 
                            ? round($agent->total_earnings / $agent->total_deliveries, 2) 
                            : 0,
                    ],
                    'quality_metrics' => [
                        'strikes_count' => $agent->strikes_count,
                        'returns_count' => $agent->returns_count,
                        'complaints_count' => $agent->complaints_count,
                    ],
                    'performance_grade' => $this->getPerformanceGrade($performanceScore),
                    'recommendations' => $this->getPerformanceRecommendations($agent, $successRate, $performanceScore),
                ];
            });

            $teamAverages = [
                'avg_success_rate' => round($agents->avg(function ($agent) {
                    return $agent->total_deliveries > 0 ? ($agent->successful_deliveries * 100.0 / $agent->total_deliveries) : 0;
                }), 2),
                'avg_rating' => round($agents->avg('rating'), 2),
                'avg_deliveries' => round($agents->avg('total_deliveries'), 2),
                'avg_earnings' => round($agents->avg('total_earnings'), 2),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'reports' => $reports,
                    'team_averages' => $teamAverages,
                    'generated_at' => Carbon::now()->toISOString(),
                ],
                'message' => 'Agent performance reports generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Agent reports error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating agent reports',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function getPerformanceBadge($score, $successRate)
    {
        if ($score >= 140 && $successRate >= 95) return '🏆 Platinum';
        if ($score >= 120 && $successRate >= 90) return '🥇 Gold';
        if ($score >= 100 && $successRate >= 85) return '🥈 Silver';
        if ($score >= 80 && $successRate >= 75) return '🥉 Bronze';
        return '📈 Developing';
    }

    private function getStatusIcon($status)
    {
        return match($status) {
            'active' => '✅',
            'warning' => '⚠️',
            'suspended' => '🚫',
            'inactive' => '❌',
            default => '❓'
        };
    }

    private function getPerformanceGrade($score)
    {
        if ($score >= 140) return 'A+';
        if ($score >= 120) return 'A';
        if ($score >= 100) return 'B+';
        if ($score >= 80) return 'B';
        if ($score >= 60) return 'C';
        return 'D';
    }

    private function getPerformanceRecommendations($agent, $successRate, $score)
    {
        $recommendations = [];

        if ($successRate < 85) {
            $recommendations[] = 'Focus on improving delivery success rate through better route planning';
        }
        if ($agent->rating < 4.0) {
            $recommendations[] = 'Work on customer service skills to improve ratings';
        }
        if ($agent->strikes_count > 2) {
            $recommendations[] = 'Address performance issues to reduce strikes';
        }
        if ($agent->total_deliveries < 20) {
            $recommendations[] = 'Increase delivery volume to build experience';
        }
        if (empty($recommendations)) {
            $recommendations[] = 'Excellent performance! Keep up the great work';
        }

        return $recommendations;
    }
}
