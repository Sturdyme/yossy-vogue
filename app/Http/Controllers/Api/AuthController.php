<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);

        $data = $this->authService->register($request->all());

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $data['user'],
            'token' => $data['token']
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
   
        $data = $this->authService->login($request->all());

        return response()->json([
            'message' => 'Login successful',
            'user' => $data['user'],
            'token' => $data['token']

        ]);

}

       public function logout(Request $request) 
       {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
       }

}