<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Resources\Game\ProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Show the signed-in learner's profile: name, grade, points, level and rewards.
     */
    public function __invoke(Request $request): ProfileResource
    {
        return new ProfileResource($request->user());
    }
}
