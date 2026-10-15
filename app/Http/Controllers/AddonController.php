<?php

namespace App\Http\Controllers;

use App\Services\Addons;
use App\Support\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The add-on gallery: teachers install add-ons for themselves, admins also for every teacher.
 */
class AddonController extends Controller
{
    public function index(Request $request, Addons $addons): Response
    {
        return Inertia::render('Addons/Index', [
            'addons' => $addons->catalog($request->user()),
            'canInstallForEveryone' => $request->user()->isAdmin(),
        ]);
    }

    public function install(Request $request, Addon $addon, Addons $addons): JsonResponse
    {
        $forEveryone = $this->forEveryone($request);
        $addons->install($addon, $request->user(), $forEveryone);

        return response()->json([
            'message' => $forEveryone ? "{$addon->label()} dipasang untuk semua guru." : "{$addon->label()} dipasang.",
            'addons' => $addons->catalog($request->user()),
        ]);
    }

    public function uninstall(Request $request, Addon $addon, Addons $addons): JsonResponse
    {
        $forEveryone = $this->forEveryone($request);
        $addons->uninstall($addon, $request->user(), $forEveryone);

        // Removed by this teacher, but an admin installed it for everyone (or, removed for
        // everyone, the admin has it installed too): it stays installed, and the message says so.
        $message = match (true) {
            ! $addons->isInstalledFor($addon, $request->user()) => "{$addon->label()} dilepas.",
            $forEveryone => "{$addon->label()} dilepas dari semua guru, tetapi tetap terpasang untuk Anda sendiri.",
            default => "{$addon->label()} tetap aktif karena dipasang admin untuk semua guru.",
        };

        return response()->json([
            'message' => $message,
            'addons' => $addons->catalog($request->user()),
        ]);
    }

    /**
     * Installing for every teacher (or removing that) is for admins.
     */
    protected function forEveryone(Request $request): bool
    {
        $forEveryone = $request->boolean('everyone');
        abort_if($forEveryone && ! $request->user()->isAdmin(), 403, 'Hanya admin yang dapat memasang add-on untuk semua guru.');

        return $forEveryone;
    }
}
