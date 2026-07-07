<?php

namespace App\AI;

class ToolRegistry
{
    public static function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_time',
                    'description' => 'Get current server time',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
        ];
    }

    public static function find(string $name)
    {
        $tools = [
            'get_time' => new class {
                public function execute($args)
                {
                    return [
                        'time' => now()->toDateTimeString(),
                    ];
                }
            },
        ];

        return $tools[$name] ?? null;
    }
}