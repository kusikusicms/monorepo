<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KusikusiCMS\Api\Actions\AttemptLogin;
use KusikusiCMS\Api\Actions\IssueToken;
use KusikusiCMS\Api\Actions\Logout;
use KusikusiCMS\Api\Http\Requests\LoginRequest;
use KusikusiCMS\Api\Http\Resources\UserResource;

class AuthController extends Controller
{
    /**
     * Exchange credentials for a personal access token.
     */
    public function login(LoginRequest $request, AttemptLogin $attempt, IssueToken $issue): JsonResponse
    {
        $user = $attempt(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        $token = $issue($user, $request->string('device_name')->toString());

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user),
        ], 201);
    }

    /**
     * Revoke the bearer token, or end the cookie session, the request was authenticated with.
     */
    public function logout(Request $request, Logout $logout): JsonResponse
    {
        $logout($request);

        return response()->json(null, 204);
    }
}
