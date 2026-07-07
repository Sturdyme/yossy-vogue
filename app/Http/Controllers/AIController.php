<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIService;

class AIController extends Controller
{
    public function chat(Request $request, AIService $aiService)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $response = $aiService->chat($request->message);

    return response()->json([
        'message' => $response,
    ]);
    }

    
}