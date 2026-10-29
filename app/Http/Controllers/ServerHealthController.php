<?php

namespace App\Http\Controllers;

use App\Services\ServerHealth;
use App\Support\DisplayTime;
use Inertia\Inertia;
use Inertia\Response;

class ServerHealthController extends Controller
{
    /**
     * Kesehatan Server, for admins: what to look at on the server before and during an exam,
     * refreshed by the page every few seconds.
     */
    public function __invoke(ServerHealth $health): Response
    {
        return Inertia::render('Reports/ServerHealth', [
            'health' => function () use ($health): array {
                $checks = $health->checks();

                return [
                    'overall' => $health->overall($checks),
                    'checks' => $checks,
                    'checkedAt' => DisplayTime::format(now(), 'H:i:s'),
                ];
            },
        ]);
    }
}
