<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    private const BASE_URL = 'https://api.open-meteo.com/v1/forecast';
    private const INSTALLATION_POWER_KW = 2.5;
    private const PANEL_EFFICIENCY = 0.2;

    public function sevenDayForecast(Request $request) {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        // Try to get response from external api, if failed return error json
        try {
            $response = Http::get(self::BASE_URL, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,sunshine_duration',
            ])->throw();
        } catch(\Exception $e) {
            return response()->json(['error' => 'Failed to fetch weather data.'], 500);
        }

        $data = $response->json();
        $daily = $data['daily'];

        $result = [];
        foreach($daily['time'] as $index => $date) {
            $maxTemp = $daily['temperature_2m_max'][$index];
            $minTemp = $daily['temperature_2m_min'][$index];
            $sunshineDurationHours = $daily['sunshine_duration'][$index] / 3600;

            $generatedEnergy = self::INSTALLATION_POWER_KW * $sunshineDurationHours * self::PANEL_EFFICIENCY;
        
            $result[] = [
                'date' => $date,
                'weather_code' => $daily['weather_code'][$index],
                'temp_min' => $minTemp,
                'temp_max' => $maxTemp,
                'estimated_energy_kwh' => round($generatedEnergy, 2),
            ];
        }

        return response()->json($result);
    }

    public function weeklySummary(Request $request) {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        try {
            $response = Http::get(self::BASE_URL, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'daily' => 'sunshine_duration,temperature_2m_max,temperature_2m_min,weather_code',
                'hourly' => 'surface_pressure'
            ]);
        } catch(\Exception $e) {
            return response()->json(['error' => 'Failed to fetch weather data.'], 500);
        }

        $data = $response->json();
        $daily = $data['daily'];
        $hourly = $data['hourly'];

        // Calculations
        $avgPressure = array_sum($hourly['surface_pressure']) / count($hourly['surface_pressure']);
        $avgSunshine = array_sum($daily['sunshine_duration']) / count($daily['sunshine_duration']);
        $minTemp = min($daily['temperature_2m_min']);
        $maxTemp = max($daily['temperature_2m_max']);

        $rainyDays = count(array_filter($daily['weather_code'], function($code) {
            return $code >= 50 && $code < 100; // Codes representing rain, drizzle, snow, thunderstorms
        }));

        $summary = $rainyDays >= 4 ? 'z opadami' : 'bez opadów';

        return response()->json([
            'avg_pressure' => round($avgPressure, 2),
            'avg_sunshine' => round($avgSunshine, 2),
            'min_temp' => $minTemp,
            'max_temp' => $maxTemp,
            'weekly_summary' => $summary,
        ]);
    }
}
