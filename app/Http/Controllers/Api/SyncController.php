<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncRequest;
use App\Models\Device;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    public function __construct(private readonly SyncService $sync) {}

    public function __invoke(SyncRequest $request): JsonResponse
    {
        $device = Device::query()->where('uuid', $request->validated('device_uuid'))->first();

        if (! $device) {
            return response()->json(['message' => 'Unknown device. Please log in again.'], 409);
        }

        $result = $this->sync->push($device, $request->user(), $request->validated());

        return response()->json([
            ...$result,
            'device' => ['uuid' => $device->uuid, 'code' => $device->code],
            'user' => $request->user()->toApiArray(),
            ...$this->sync->referenceData($request->validated('customers_since'), $request->validated('database_id')),
        ]);
    }
}
