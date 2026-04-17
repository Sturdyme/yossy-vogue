<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected $baseUrl = 'https://api.paystack.co';
    public function initialize($data)
   {
     $reference = Str::uuid();

     $amountKobo = (int) ($data['amount'] * 100); // convert to kobo, ensure integer
     $response = Http::withToken(config('services.paystack.secret_key'))->post("{$this->baseUrl}/transaction/initialize", [
        'email' => $data['email'],
        'amount' => $amountKobo,
        'reference' => $reference,
        'callback_url' => route('payment.callback'),
        'metadata' => $data['metadata'] ?? []
     ]);

     $result = $response->json();

     if (!$result['status']) {
        \Log::error('Paystack initialization failed', [
            'response' => $result
        ]);
        throw new \Exception('Paystack initialization failed: ' . ($result['message'] ?? 'Unknown error'));
     }

     return [
        'authorization_url' => $result['data']['authorization_url'],
        'reference' => $reference
     ];
   }

   public function verify($reference)
   {
      $response = Http::withToken(config('services.paystack.secret_key'))->get("{$this->baseUrl}/transaction/verify/{$reference}");
      return $response->json();
   }

}
