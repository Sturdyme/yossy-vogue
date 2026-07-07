<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected string $baseUrl = 'https://api.paystack.co';

    public function initialize(array $data): array
    {
        // Generate a clean string reference if one isn't already provided
        $reference = $data['reference'] ?? (string) Str::uuid();

       
        $amountKobo = (int) $data['amount']; 

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email'        => $data['email'],
                'amount'       => $amountKobo,
                'reference'    => $reference,
                'callback_url' => route('payment.callback'),
                'metadata'     => $data['metadata'] ?? []
            ]);

        $result = $response->json();

        if (empty($result['status']) || !$result['status']) {
            \Log::error('Paystack initialization failed', [
                'response' => $result
            ]);
            throw new \Exception('Paystack initialization failed: ' . ($result['message'] ?? 'Unknown error'));
        }

        return [
            'authorization_url' => $result['data']['authorization_url'],
            'reference'         => $reference
        ];
    }

    public function verify(string $reference): array
    {
      
        $cleanReference = trim($reference);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("{$this->baseUrl}/transaction/verify/{$cleanReference}");
            
        return $response->json();
    }
}