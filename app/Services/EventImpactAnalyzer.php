<?php

namespace App\Services;

use App\Models\EventImpact;
use App\Models\DemandForecast;
use App\Models\DeliveryAgent;
use App\Models\SeasonalPattern;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EventImpactAnalyzer
{
    private $weatherApiKey;
    private $eventSources = [
        'weather' => 'openweathermap',
        'economic' => 'cbn_nigeria',
        'social' => 'nigerian_calendar',
        'transport' => 'traffic_api'
    ];

    public function __construct()
    {
        $this->weatherApiKey = env('OPENWEATHER_API_KEY', 'demo_key');
    }

    /**
     * Analyze all types of events and their potential impact
     */
    public function analyzeAllEvents($daysAhead = 30)
    {
        $results = [];
        
        // Analyze weather events
        $results['weather'] = $this->analyzeWeatherEvents($daysAhead);
        
        // Analyze economic events
        $results['economic'] = $this->analyzeEconomicEvents($daysAhead);
        
        // Analyze social/cultural events
        $results['social'] = $this->analyzeSocialEvents($daysAhead);
        
        // Analyze transport disruptions
        $results['transport'] = $this->analyzeTransportEvents($daysAhead);
        
        return $results;
    }

    /**
     * Analyze weather events and their impact on demand
     */
    public function analyzeWeatherEvents($daysAhead = 30)
    {
        $weatherEvents = [];
        $regions = $this->getNigerianRegions();
        
        foreach ($regions as $region) {
            $forecast = $this->getWeatherForecast($region, $daysAhead);
            $impacts = $this->calculateWeatherImpacts($forecast, $region);
            
            foreach ($impacts as $impact) {
                $weatherEvents[] = $this->createEventImpact([
                    'event_type' => 'weather',
                    'event_name' => $impact['event_name'],
                    'event_date' => $impact['date'],
                    'impact_duration_days' => $impact['duration'],
                    'demand_impact' => $impact['demand_change'],
                    'affected_locations' => [$region['state']],
                    'severity' => $impact['severity'],
                    'external_data' => $impact['weather_data'],
                    'impact_description' => $impact['description']
                ]);
            }
        }
        
        return $weatherEvents;
    }

    /**
     * Analyze economic events (salary days, market days, etc.)
     */
    public function analyzeEconomicEvents($daysAhead = 30)
    {
        $economicEvents = [];
        $currentDate = Carbon::today();
        
        // Government salary days (typically last working day of month)
        for ($i = 0; $i <= $daysAhead; $i++) {
            $date = $currentDate->copy()->addDays($i);
            
            // Check if it's end of month (last working day)
            if ($this->isGovernmentSalaryDay($date)) {
                $economicEvents[] = $this->createEventImpact([
                    'event_type' => 'economic',
                    'event_name' => 'Government Salary Day',
                    'event_date' => $date,
                    'impact_duration_days' => 5,
                    'demand_impact' => 25, // 25% increase
                    'affected_locations' => $this->getAllStates(),
                    'severity' => 'medium',
                    'external_data' => ['source' => 'government_schedule'],
                    'impact_description' => 'Increased purchasing power following government salary payments'
                ]);
            }
            
            // Market days (typically every 4 or 8 days in rural areas)
            if ($this->isMarketDay($date)) {
                $economicEvents[] = $this->createEventImpact([
                    'event_type' => 'economic',
                    'event_name' => 'Traditional Market Day',
                    'event_date' => $date,
                    'impact_duration_days' => 1,
                    'demand_impact' => 40, // 40% increase on market days
                    'affected_locations' => $this->getRuralStates(),
                    'severity' => 'medium',
                    'external_data' => ['market_type' => 'traditional'],
                    'impact_description' => 'Traditional market day increases local demand'
                ]);
            }
        }
        
        return $economicEvents;
    }

    /**
     * Analyze social and cultural events
     */
    public function analyzeSocialEvents($daysAhead = 30)
    {
        $socialEvents = [];
        
        // Nigerian holidays and festivals
        $nigerianEvents = $this->getNigerianHolidays($daysAhead);
        
        foreach ($nigerianEvents as $event) {
            $socialEvents[] = $this->createEventImpact([
                'event_type' => 'social',
                'event_name' => $event['name'],
                'event_date' => $event['date'],
                'impact_duration_days' => $event['duration'],
                'demand_impact' => $event['demand_impact'],
                'affected_locations' => $event['affected_regions'],
                'severity' => $event['severity'],
                'external_data' => ['holiday_type' => $event['type']],
                'impact_description' => $event['description']
            ]);
        }
        
        return $socialEvents;
    }

    /**
     * Analyze transport disruptions
     */
    public function analyzeTransportEvents($daysAhead = 30)
    {
        $transportEvents = [];
        
        // Simulate transport disruption analysis
        // In production, integrate with traffic APIs, road closure data, etc.
        
        $potentialDisruptions = [
            [
                'name' => 'Lagos-Ibadan Expressway Maintenance',
                'probability' => 0.3,
                'impact' => -15,
                'duration' => 3,
                'affected_states' => ['Lagos', 'Ogun', 'Oyo']
            ],
            [
                'name' => 'Port Harcourt Bridge Closure',
                'probability' => 0.1,
                'impact' => -25,
                'duration' => 7,
                'affected_states' => ['Rivers', 'Bayelsa']
            ]
        ];
        
        foreach ($potentialDisruptions as $disruption) {
            if (rand(1, 100) <= ($disruption['probability'] * 100)) {
                $eventDate = Carbon::today()->addDays(rand(1, $daysAhead));
                
                $transportEvents[] = $this->createEventImpact([
                    'event_type' => 'transport',
                    'event_name' => $disruption['name'],
                    'event_date' => $eventDate,
                    'impact_duration_days' => $disruption['duration'],
                    'demand_impact' => $disruption['impact'],
                    'affected_locations' => $disruption['affected_states'],
                    'severity' => abs($disruption['impact']) > 20 ? 'high' : 'medium',
                    'external_data' => ['disruption_type' => 'road_closure'],
                    'impact_description' => 'Transport disruption affecting goods movement and demand'
                ]);
            }
        }
        
        return $transportEvents;
    }

    /**
     * Apply event impacts to existing forecasts
     */
    public function applyEventImpactsToForecasts()
    {
        $activeEvents = EventImpact::where('event_date', '>=', Carbon::today())
            ->where('event_date', '<=', Carbon::today()->addDays(30))
            ->get();
        $adjustedForecasts = 0;
        
        foreach ($activeEvents as $event) {
            // Find forecasts that overlap with the event
            $affectedForecasts = DemandForecast::whereHas('deliveryAgent', function($query) use ($event) {
                $query->whereIn('state', $event->affected_locations ?? []);
            })
            ->whereBetween('forecast_date', [
                $event->event_date,
                $event->event_date->addDays($event->impact_duration_days)
            ])
            ->get();
            
            foreach ($affectedForecasts as $forecast) {
                $adjustmentFactor = 1 + ($event->demand_impact / 100);
                $newDemand = round($forecast->predicted_demand * $adjustmentFactor);
                
                $forecast->update([
                    'predicted_demand' => $newDemand,
                    'confidence_score' => max(60, $forecast->confidence_score - 5),
                    'input_factors' => array_merge(
                        $forecast->input_factors ?? [],
                        ['event_adjustment' => $event->demand_impact, 'event_id' => $event->id]
                    )
                ]);
                
                $adjustedForecasts++;
            }
        }
        
        return $adjustedForecasts;
    }

    // HELPER METHODS

    private function getWeatherForecast($region, $days)
    {
        // Simulate weather API call
        // In production, use actual weather API
        
        $cacheKey = "weather_forecast_{$region['code']}_{$days}";
        
        return Cache::remember($cacheKey, now()->addHours(6), function() use ($region, $days) {
            // Simulate weather data
            $forecast = [];
            
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::today()->addDays($i);
                $forecast[] = [
                    'date' => $date,
                    'temperature' => rand(20, 35),
                    'humidity' => rand(40, 90),
                    'rainfall' => rand(0, 50),
                    'wind_speed' => rand(5, 25),
                    'weather_condition' => $this->getRandomWeatherCondition()
                ];
            }
            
            return $forecast;
        });
    }

    private function calculateWeatherImpacts($forecast, $region)
    {
        $impacts = [];
        
        foreach ($forecast as $day) {
            // Heavy rainfall impact
            if ($day['rainfall'] > 30) {
                $impacts[] = [
                    'event_name' => 'Heavy Rainfall',
                    'date' => $day['date'],
                    'duration' => 1,
                    'demand_change' => -15, // 15% decrease
                    'severity' => $day['rainfall'] > 40 ? 'high' : 'medium',
                    'weather_data' => $day,
                    'description' => 'Heavy rainfall reducing mobility and demand'
                ];
            }
            
            // Extreme heat impact
            if ($day['temperature'] > 38) {
                $impacts[] = [
                    'event_name' => 'Extreme Heat',
                    'date' => $day['date'],
                    'duration' => 1,
                    'demand_change' => 10, // 10% increase (more beverage demand)
                    'severity' => 'medium',
                    'weather_data' => $day,
                    'description' => 'Extreme heat increasing demand for refreshments'
                ];
            }
        }
        
        return $impacts;
    }

    private function getNigerianRegions()
    {
        return [
            ['code' => 'SW', 'state' => 'Lagos', 'lat' => 6.5244, 'lng' => 3.3792],
            ['code' => 'NC', 'state' => 'Abuja', 'lat' => 9.0765, 'lng' => 7.3986],
            ['code' => 'SE', 'state' => 'Enugu', 'lat' => 6.5244, 'lng' => 7.5102],
            ['code' => 'SS', 'state' => 'Port Harcourt', 'lat' => 4.8156, 'lng' => 7.0498],
            ['code' => 'NW', 'state' => 'Kano', 'lat' => 12.0022, 'lng' => 8.5920],
            ['code' => 'NE', 'state' => 'Maiduguri', 'lat' => 11.8333, 'lng' => 13.1500]
        ];
    }

    private function isGovernmentSalaryDay($date)
    {
        // Last working day of the month
        $lastDayOfMonth = $date->copy()->endOfMonth();
        
        // If last day is weekend, move to Friday
        while ($lastDayOfMonth->isWeekend()) {
            $lastDayOfMonth->subDay();
        }
        
        return $date->isSameDay($lastDayOfMonth);
    }

    private function isMarketDay($date)
    {
        // Traditional Nigerian markets often run on 4 or 8-day cycles
        return $date->dayOfYear % 4 === 0;
    }

    private function getNigerianHolidays($daysAhead)
    {
        $holidays = [];
        $currentYear = Carbon::now()->year;
        
        // Fixed holidays
        $fixedHolidays = [
            ['name' => 'New Year Day', 'date' => "{$currentYear}-01-01", 'impact' => 50, 'duration' => 2],
            ['name' => 'Workers Day', 'date' => "{$currentYear}-05-01", 'impact' => 30, 'duration' => 1],
            ['name' => 'Independence Day', 'date' => "{$currentYear}-10-01", 'impact' => 40, 'duration' => 1],
            ['name' => 'Christmas Day', 'date' => "{$currentYear}-12-25", 'impact' => 80, 'duration' => 3],
            ['name' => 'Boxing Day', 'date' => "{$currentYear}-12-26", 'impact' => 60, 'duration' => 1]
        ];
        
        foreach ($fixedHolidays as $holiday) {
            $holidayDate = Carbon::parse($holiday['date']);
            
            if ($holidayDate->isFuture() && $holidayDate->diffInDays() <= $daysAhead) {
                $holidays[] = [
                    'name' => $holiday['name'],
                    'date' => $holidayDate,
                    'duration' => $holiday['duration'],
                    'demand_impact' => $holiday['impact'],
                    'affected_regions' => $this->getAllStates(),
                    'severity' => $holiday['impact'] > 60 ? 'high' : 'medium',
                    'type' => 'national',
                    'description' => "National holiday: {$holiday['name']}"
                ];
            }
        }
        
        return $holidays;
    }

    private function getAllStates()
    {
        return [
            'Lagos', 'Abuja', 'Kano', 'Rivers', 'Oyo', 'Kaduna', 'Ogun', 'Imo',
            'Anambra', 'Plateau', 'Borno', 'Osun', 'Delta', 'Edo', 'Kwara'
        ];
    }

    private function getRuralStates()
    {
        return ['Borno', 'Yobe', 'Adamawa', 'Taraba', 'Bauchi', 'Gombe', 'Kebbi', 'Sokoto'];
    }

    private function getRandomWeatherCondition()
    {
        $conditions = ['clear', 'cloudy', 'rainy', 'thunderstorm', 'misty'];
        return $conditions[array_rand($conditions)];
    }

    private function createEventImpact($data)
    {
        return EventImpact::create($data);
    }
}
