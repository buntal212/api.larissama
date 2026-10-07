<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegisterWarung;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterWarungRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\WarungResource;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function store(RegisterWarungRequest $request, RegisterWarung $registerWarung): JsonResponse
    {
        $result = $registerWarung->execute($request->validated());

        return response()->json([
            'data' => [
                'warung' => (new WarungResource($result['warung']))->resolve($request),
                'owner' => (new UserResource($result['owner']))->resolve($request),
                'status_pendaftaran' => 'menunggu_persetujuan',
            ],
        ], 201);
    }
}
