<?php

namespace App\Http\Controllers;

use App\Services\OnlineAddress;
use Illuminate\Http\JsonResponse;

class OnlineAddressController extends Controller
{
    /**
     * The current address of the server on the internet, for the top bar of admins and teachers:
     * asked again by their pages every half minute, so a new address shows by itself.
     */
    public function __invoke(OnlineAddress $onlineAddress): JsonResponse
    {
        return response()->json([
            // Without a tunnel the server is for the school network only: nothing to show.
            'configured' => $onlineAddress->isConfigured(),
            'url' => $onlineAddress->url(),
        ]);
    }
}
