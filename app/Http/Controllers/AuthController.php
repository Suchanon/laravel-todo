<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        //find unique user via email
        $user = User::where('email', $credentials['email'])->first();

        $incorrectCredentials = ! $user || ! Hash::check($credentials['password'], $user->password);

        if ($incorrectCredentials) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.']
            ]); // 422
        }

        $tokenName = 'bank-api-token-name';
        return response()->json([
            'token' => $user->createToken($tokenName)->plainTextToken
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out naaa']);
    }

    public function register(Request $request): JsonResponse
    {
        //validate 
        $validatedUser = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users'],   // ← unique กันสมัครซ้ำ
            'password' => ['required', 'min:8'],
        ]);
        $user = User::create($validatedUser);
        //create user to db

        //return access toekn?
        $tokenName = 'bank-api-token-name';
        return response()->json([
            'token' => $user->createToken($tokenName)->plainTextToken
        ], 201);
    }
}
