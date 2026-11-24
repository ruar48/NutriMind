<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\LoginRequest;
use App\Http\Resources\Game\ProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

class LoginController extends Controller
{
    /**
     * Sign a learner in to the game and hand out an API token.
     *
     * The game sends the token on every later request as "Authorization: Bearer <token>". Each sign-in creates a
     * new token, so a learner can stay signed in on several devices.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $student = $request->authenticate();

        User::withoutTimestamps(function () use ($student): void {
            $student->forceFill(['last_login_at' => now()])->save();
        });

        return (new ProfileResource($student))
            ->additional([
                'token' => $student->createToken($request->deviceName())->plainTextToken,
                'token_type' => 'Bearer',
            ])
            ->response();
    }

    /**
     * Sign the game out on this device by revoking the token it used.
     */
    public function destroy(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }
}
