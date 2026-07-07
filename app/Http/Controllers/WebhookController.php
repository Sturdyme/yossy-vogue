<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        if ($payload['event'] === 'charge.success') {
            $reference = $payload['data']['reference'];

            Payment::where('reference', $reference)->update([
                'status' => 'success'
            ]);
        }
        return response()->json(['status' => 'ok']);
    }
}
