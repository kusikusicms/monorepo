<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use KusikusiCMS\Api\Actions\StartSession;
use KusikusiCMS\Api\Http\Requests\SessionLoginRequest;
use KusikusiCMS\Api\Http\Resources\UserResource;

class SessionController extends Controller
{
    /**
     * Sign a first-party SPA in with a session cookie (call GET /sanctum/csrf-cookie first).
     */
    public function store(SessionLoginRequest $request, StartSession $start): JsonResponse
    {
        $user = $start(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
            $request,
        );

        return response()->json(['data' => UserResource::make($user)]);
    }
}
