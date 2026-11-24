<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SyncProgressRequest;
use App\Http\Resources\Game\ProfileResource;
use App\Models\User;
use App\Services\Game\ProgressSync;

class ProgressSyncController extends Controller
{
    /**
     * Store the progress in the save file the game uploaded and answer with the updated profile.
     *
     * "ignored" lists the game mission IDs no mission is mapped to and the reward names no active reward has, so
     * an administrator can see what still needs a game mission ID or a reward. After a successful sync the game
     * may clear its save's needsSync flag.
     */
    public function __invoke(SyncProgressRequest $request, ProgressSync $sync): ProfileResource
    {
        /** @var User $student */
        $student = $request->user();

        $ignored = $sync->sync($student, $request->validated());

        return (new ProfileResource($student->fresh()))->additional(['ignored' => $ignored]);
    }
}
