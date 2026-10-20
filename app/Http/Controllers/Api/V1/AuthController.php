<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->tokenResponse($this->guard()->login($user), $user, Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->guard()->attempt($request->validated());

        if (! $token) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
                'error' => 'invalid_credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->tokenResponse($token, $this->guard()->user());
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Exchange a valid (or recently expired, within JWT_REFRESH_TTL) token
     * for a new one. The old token is blacklisted.
     */
    public function refresh(): JsonResponse
    {
        $token = $this->guard()->refresh();

        return $this->tokenResponse($token, $this->guard()->setToken($token)->user());
    }

    public function logout(): Response
    {
        $this->guard()->logout();

        return response()->noContent();
    }

    private function tokenResponse(string $token, User $user, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->guard()->factory()->getTTL() * 60,
                'user' => new UserResource($user),
            ],
        ], $status);
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard */
        return Auth::guard('api');
    }
}
