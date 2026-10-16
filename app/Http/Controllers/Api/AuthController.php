<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly SyncService $sync) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_uuid' => ['required', 'uuid'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Incorrect username or password.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'This account has been deactivated. Please contact the owner.',
            ]);
        }

        $device = Device::register($credentials['device_uuid'], $credentials['device_name'] ?? null);
        $device->update(['last_user_id' => $user->id]);

        $token = $user->createToken('device:'.$device->code)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->toApiArray(),
            'device' => ['uuid' => $device->uuid, 'code' => $device->code],
            ...$this->sync->referenceData(null),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
