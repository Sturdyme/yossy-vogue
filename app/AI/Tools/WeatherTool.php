<?php

namespace App\AI\Tools;

class WeatherTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Get current weather for a city';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'City name',
                ],
            ],
            'required' => ['city'],
        ];
    }

    public function execute(array $arguments): mixed
    {
        $city = $arguments['city'] ?? 'Unknown';

        // Fake response (replace with real API later)
        return [
            'city' => $city,
            'temperature' => '32°C',
            'condition' => 'Sunny',
        ];
    }

}