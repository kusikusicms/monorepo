<?php

namespace KusikusiCMS\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use KusikusiCMS\Api\Api;
use KusikusiCMS\Api\Support\Capabilities;
use KusikusiCMS\Api\Support\Capability;

class CapabilitiesController extends Controller
{
    /**
     * Public description of the API: version, auth modes and the packages that contribute to it.
     */
    public function __invoke(Capabilities $capabilities): JsonResponse
    {
        $auth = ['modes' => ['token']];

        if (Api::spaEnabled()) {
            $auth['modes'][] = 'session';
            $auth['csrf_cookie_url'] = '/'.trim((string) config('sanctum.prefix', 'sanctum'), '/').'/csrf-cookie';
        }

        return response()->json([
            'data' => [
                'api' => ['version' => Api::VERSION, 'auth' => $auth],
                'packages' => array_map(fn (Capability $capability) => $capability->toArray(), $capabilities->all()),
            ],
        ]);
    }
}
