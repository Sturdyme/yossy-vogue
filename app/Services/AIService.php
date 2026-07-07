<?php

namespace App\Services;

use App\AI\ToolRegistry;
use OpenAI\Client;

class AIService
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function chat(string $userMessage): string
    {
        $tools = ToolRegistry::definitions();

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful AI assistant.',
            ],
            [
                'role' => 'user',
                'content' => $userMessage,
            ],
        ];

       while (true) {
    try {
        // ✅ Add delay to prevent rate limit
        usleep(500000); // 0.5 seconds

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools ?? [],
        ]);

    } catch (\OpenAI\Exceptions\RateLimitException $e) {
        // ✅ Handle rate limit properly (NO recursion)
        return 'Rate limit exceeded. Please wait a few seconds and try again.';
    } catch (\Throwable $e) {
        // ✅ Catch any other hidden errors
        return 'Error: ' . $e->getMessage();
    }

    $message = $response->choices[0]->message;

    // If no tool calls → return final response
    if (empty($message->toolCalls)) {
        return $message->content ?? '';
    }

    // Add assistant message with tool calls
    $messages[] = [
        'role' => 'assistant',
        'content' => $message->content,
        'tool_calls' => $message->toolCalls,
    ];

    foreach ($message->toolCalls as $toolCall) {
        $toolName = $toolCall->function->name;
        $arguments = json_decode($toolCall->function->arguments, true) ?? [];

        $tool = ToolRegistry::find($toolName);

        if (!$tool) {
            throw new \Exception("Tool '{$toolName}' not found");
        }

        $result = $tool->execute($arguments);

        // Send tool result back to model
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall->id,
            'content' => json_encode($result),
        ];
    }
}
    }
}