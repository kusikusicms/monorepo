<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use KusikusiCMS\Api\Http\Resources\UserResource;

class MeController extends Controller
{
    /**
     * The authenticated user.
     */
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
