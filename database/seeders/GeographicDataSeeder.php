<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GeographicZone;
use App\Models\DADistanceMatrix;

class GeographicDataSeeder extends Seeder
{
    public function run()
    {
        // Create Geographic Zones
        $zones = [
            ['zone_code' => 'SW', 'zone_name' => 'South West', 'states_included' => ['Lagos', 'Ogun', 'Oyo', 'Osun', 'Ondo', 'Ekiti']],
            ['zone_code' => 'SE', 'zone_name' => 'South East', 'states_included' => ['Anambra', 'Enugu', 'Imo', 'Abia', 'Ebonyi']],
            ['zone_code' => 'SS', 'zone_name' => 'South South', 'states_included' => ['Rivers', 'Delta', 'Bayelsa', 'Cross River', 'Akwa Ibom', 'Edo']],
            ['zone_code' => 'NC', 'zone_name' => 'North Central', 'states_included' => ['Abuja', 'Niger', 'Kogi', 'Kwara', 'Nasarawa', 'Plateau', 'Benue']],
            ['zone_code' => 'NE', 'zone_name' => 'North East', 'states_included' => ['Borno', 'Yobe', 'Adamawa', 'Taraba', 'Bauchi', 'Gombe']],
            ['zone_code' => 'NW', 'zone_name' => 'North West', 'states_included' => ['Kano', 'Kaduna', 'Katsina', 'Zamfara', 'Sokoto', 'Kebbi', 'Jigawa']]
        ];

        foreach ($zones as $zone) {
            GeographicZone::create([
                'zone_code' => $zone['zone_code'],
                'zone_name' => $zone['zone_name'],
                'states_included' => $zone['states_included'],
                'avg_transport_cost_per_km' => rand(30, 80),
                'seasonal_patterns' => [
                    'peak_months' => ['December', 'January', 'September'],
                    'low_months' => ['March', 'April', 'May']
                ]
            ]);
        }

        // Sample Distance Matrix Data
        $this->seedDistanceMatrix();
    }

    private function seedDistanceMatrix()
    {
        $sampleDistances = [
            ['from' => 1, 'to' => 2, 'distance' => 85, 'time' => 120, 'cost' => 4250],
            ['from' => 1, 'to' => 3, 'distance' => 340, 'time' => 480, 'cost' => 17000],
            ['from' => 2, 'to' => 3, 'distance' => 290, 'time' => 420, 'cost' => 14500],
            ['from' => 3, 'to' => 4, 'distance' => 180, 'time' => 270, 'cost' => 9000],
        ];

        foreach ($sampleDistances as $distance) {
            DADistanceMatrix::create([
                'from_da_id' => $distance['from'],
                'to_da_id' => $distance['to'],
                'distance_km' => $distance['distance'],
                'travel_time_minutes' => $distance['time'],
                'transport_cost' => $distance['cost'],
                'route_quality' => 'good'
            ]);
        }
    }
} 