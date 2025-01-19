<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherControllerTest extends TestCase
{
    /**
     * Test endpoint with valid parameters.
     *
     * @return void
     */
    public function test_sevenDayForecast_success() {
        // Mock API response
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'daily' => [
                    'time' => ['2025-01-17', '2025-01-18'],
                    'weather_code' => [3, 2],
                    'temperature_2m_max' => [5, 10],
                    'temperature_2m_min' => [0, 1],
                    'sunshine_duration' => [36000, 28800], // 10h, 8h
                ],
            ], 200),
        ]);
        
        // Call endpoint
        $response = $this->getJson('/api/forecast?latitude=49.3814&longitude=22.1446');

        // Assertions
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'date',
                'weather_code',
                'temp_min',
                'temp_max',
                'estimated_energy_kwh',
            ],
        ]);
        $response->assertJson([
            [
                'date' => '2025-01-17',
                'weather_code' => 3,
                'temp_min' => 0,
                'temp_max' => 5,
                'estimated_energy_kwh' => 5.00, // 2.5 * 10 * 0.2
            ],
            [
                'date' => '2025-01-18',
                'weather_code' => 2,
                'temp_min' => 1,
                'temp_max' => 10,
                'estimated_energy_kwh' => 4.00, // 2.5 * 8 * 0.2
            ],
        ]);
    }
    
    /**
     * Test endpoint with invalid params.
     *
     * @return void
     */
    public function test_sevenDayForecast_invalid_params() {
        $response = $this->getJson('/api/forecast?latitude=invalid&longitude=invalid');

        $response->assertStatus(422); // Unprocessable Entity
        $response->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /**
     * Test endpoint with missing parameters.
     *
     * @return void
     */
    public function test_sevenDayForecast_missing_params() {
        $response = $this->getJson('/api/forecast');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    /**
     * Test when external API fails.
     *
     * @return void
     */
    public function test_sevenDayForecast_external_api_fail() {
        // Mock failed API response
        Http::fake([
            'api.open-meteo.com/*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/forecast?latitude=49.3814&longitude=22.1446');

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Failed to fetch weather data.',
        ]);
    }

    public function test_weeklySummary_success() {
        // Mock API response
        Http::fake([
            '*' => Http::response([
                'daily' => [
                    'sunshine_duration' => [3600, 7200, 1800, 0, 3600, 7200, 3600],
                    'temperature_2m_max' => [10, 12, 8, 15, 5, 7, 6],
                    'temperature_2m_min' => [-5, -3, -6, -1, -2, -4, 0],
                    'weather_code' => [1, 2, 50, 61, 62, 80, 3]
                ],
                'hourly' => [
                    'surface_pressure' => [1000, 1002, 998, 995, 994, 996, 999]
                ]
            ], 200)
        ]);

        $response = $this->getJson('/api/weeklySummary?latitude=49.38529&longitude=22.15361');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'avg_pressure',
            'avg_sunshine',
            'min_temp',
            'max_temp',
            'weekly_summary'
        ]);

        $response->assertJson([
            'avg_pressure' => 997.71,
            'avg_sunshine' => 3857.14,
            'min_temp' => -6,
            'max_temp' => 15,
            'weekly_summary' => 'z opadami'
        ]);
    }

    public function test_weeklySummary_invalid_params() {
        $response = $this->getJson('/api/weeklySummary?latitude=invalid&longitude=invalid');

        $response->assertStatus(422); // Unprocessable Entity
        $response->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_weeklySummary_missing_params() {
        $response = $this->getJson('/api/weeklySummary');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_weeklySummary_external_api_fail() {
        // Mock failed API response
        Http::fake([
            'api.open-meteo.com/*' => Http::response([], 500),
        ]);

        $response = $this->getJson('/api/weeklySummary?latitude=49.3814&longitude=22.1446');

        $response->assertStatus(500);
        $response->assertJson([
            'error' => 'Failed to fetch weather data.',
        ]);
    }
}
