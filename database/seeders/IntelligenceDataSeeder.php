<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EventImpact;
use App\Models\RiskAssessment;
use App\Models\AutomatedDecision;
use App\Models\PredictionAccuracy;
use App\Models\MarketIntelligence;
use App\Models\DeliveryAgent;
use Carbon\Carbon;

class IntelligenceDataSeeder extends Seeder
{
    public function run()
    {
        $this->createEventImpacts();
        $this->createRiskAssessments();
        $this->createAutomatedDecisions();
        $this->createPredictionAccuracy();
        $this->createMarketIntelligence();
    }

    private function createEventImpacts()
    {
        $events = [
            [
                'event_type' => 'weather',
                'event_name' => 'Heavy Rainfall',
                'event_date' => Carbon::today()->addDays(3),
                'impact_duration_days' => 2,
                'demand_impact' => -20,
                'affected_locations' => ['Lagos', 'Ogun'],
                'severity' => 'medium',
                'external_data' => ['rainfall_mm' => 45, 'wind_speed' => 15],
                'impact_description' => 'Heavy rainfall expected to reduce mobility and demand'
            ],
            [
                'event_type' => 'economic',
                'event_name' => 'Government Salary Day',
                'event_date' => Carbon::today()->addDays(7),
                'impact_duration_days' => 5,
                'demand_impact' => 30,
                'affected_locations' => ['Lagos', 'Abuja', 'Kano'],
                'severity' => 'medium',
                'external_data' => ['salary_type' => 'federal_government'],
                'impact_description' => 'Government salary payments increase purchasing power'
            ],
            [
                'event_type' => 'social',
                'event_name' => 'Eid Celebration',
                'event_date' => Carbon::today()->addDays(15),
                'impact_duration_days' => 3,
                'demand_impact' => 60,
                'affected_locations' => ['Kano', 'Kaduna', 'Sokoto'],
                'severity' => 'high',
                'external_data' => ['holiday_type' => 'religious'],
                'impact_description' => 'Eid celebration significantly increases demand'
            ]
        ];

        foreach ($events as $event) {
            EventImpact::create($event);
        }
    }

    private function createRiskAssessments()
    {
        $das = DeliveryAgent::take(10)->get();
        
        foreach ($das as $da) {
            RiskAssessment::create([
                'delivery_agent_id' => $da->id,
                'assessment_date' => Carbon::today(),
                'stockout_probability' => rand(10, 90),
                'overstock_probability' => rand(5, 40),
                'days_until_stockout' => rand(1, 30),
                'potential_lost_sales' => rand(1000, 10000),
                'carrying_cost_risk' => rand(500, 5000),
                'risk_level' => ['low', 'medium', 'high'][rand(0, 2)],
                'risk_factors' => [
                    'demand_variability' => rand(10, 50),
                    'supply_uncertainty' => rand(5, 30),
                    'seasonal_impact' => rand(0, 40)
                ],
                'mitigation_suggestions' => [
                    'Increase safety stock',
                    'Improve demand forecasting',
                    'Optimize reorder timing'
                ],
                'overall_risk_score' => rand(20, 95)
            ]);
        }
    }

    private function createAutomatedDecisions()
    {
        $das = DeliveryAgent::take(5)->get();
        
        foreach ($das as $da) {
            AutomatedDecision::create([
                'decision_type' => ['reorder', 'stock_adjustment', 'transfer'][rand(0, 2)],
                'delivery_agent_id' => $da->id,
                'trigger_reason' => 'Automated optimization trigger',
                'decision_data' => [
                    'quantity' => rand(10, 50),
                    'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                    'estimated_cost' => rand(1000, 5000)
                ],
                'confidence_score' => rand(70, 95),
                'status' => ['pending', 'executed'][rand(0, 1)],
                'triggered_at' => Carbon::now()->subHours(rand(1, 24))
            ]);
        }
    }

    private function createPredictionAccuracy()
    {
        $models = ['arima', 'neural_network', 'random_forest', 'ensemble'];
        
        foreach ($models as $model) {
            PredictionAccuracy::create([
                'model_name' => $model,
                'prediction_type' => 'demand_forecast',
                'evaluation_date' => Carbon::today(),
                'accuracy_percentage' => rand(75, 95),
                'mean_absolute_error' => rand(2, 8),
                'root_mean_square_error' => rand(3, 10),
                'total_predictions' => rand(100, 500),
                'correct_predictions' => rand(80, 450),
                'performance_metrics' => [
                    'precision' => rand(80, 95),
                    'recall' => rand(75, 90),
                    'f1_score' => rand(78, 92)
                ],
                'model_parameters' => [
                    'learning_rate' => 0.01,
                    'epochs' => 100,
                    'batch_size' => 32
                ]
            ]);
        }
    }

    private function createMarketIntelligence()
    {
        $regions = ['SW', 'NC', 'SE', 'SS', 'NW', 'NE'];
        
        foreach ($regions as $region) {
            MarketIntelligence::create([
                'region_code' => $region,
                'intelligence_date' => Carbon::today(),
                'market_temperature' => rand(30, 90),
                'demand_drivers' => [
                    'economic_growth' => rand(1, 10),
                    'population_growth' => rand(1, 5),
                    'urbanization' => rand(1, 8)
                ],
                'supply_constraints' => [
                    'transportation' => rand(1, 10),
                    'storage' => rand(1, 7),
                    'distribution' => rand(1, 8)
                ],
                'price_sensitivity' => rand(20, 80),
                'competitor_activity' => [
                    'new_entrants' => rand(0, 5),
                    'price_changes' => rand(-10, 10),
                    'market_share_shift' => rand(-5, 5)
                ],
                'external_indicators' => [
                    'inflation_rate' => rand(5, 15),
                    'unemployment_rate' => rand(10, 30),
                    'gdp_growth' => rand(1, 8)
                ],
                'market_summary' => 'Market conditions are favorable with moderate growth potential',
                'reliability_score' => rand(70, 95)
            ]);
        }
    }
}
