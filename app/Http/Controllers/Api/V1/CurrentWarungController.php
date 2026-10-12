<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CurrentWarungLogoRequest;
use App\Http\Requests\Api\V1\CurrentWarungUpdateRequest;
use App\Http\Resources\Api\V1\WarungResource;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CurrentWarungController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);
        Gate::authorize('viewCurrent', Warung::class);

        $warung = $user->warung;
        abort_if($warung === null, 404);

        return response()->json([
            'data' => (new WarungResource($warung))->resolve($request),
        ]);
    }

    public function update(CurrentWarungUpdateRequest $request): JsonResponse
    {
        $warung = $this->currentWarung($request);
        $warung->fill($request->validated());
        $warung->save();

        return $this->response($request, $warung);
    }

    public function storeLogo(CurrentWarungLogoRequest $request): JsonResponse
    {
        $warung = $this->currentWarung($request);
        $file = $request->file('logo');
        abort_unless($file !== null, 422);

        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => abort(422),
        };
        $directory = 'warung-logos/'.$warung->getKey();
        $newLogoPath = $file->storeAs($directory, Str::uuid()->toString().'.'.$extension, 'public');
        abort_if($newLogoPath === false, 500, 'Logo tidak dapat disimpan.');
        $previousLogoPath = $warung->logo;

        try {
            $warung->logo = $newLogoPath;
            $warung->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newLogoPath);

            throw $exception;
        }

        $this->deleteManagedLogo($previousLogoPath, $warung);

        return $this->response($request, $warung);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $warung = $this->currentWarung($request);
        $previousLogoPath = $warung->logo;
        $warung->logo = null;
        $warung->save();
        $this->deleteManagedLogo($previousLogoPath, $warung);

        return $this->response($request, $warung);
    }

    private function currentWarung(Request $request): Warung
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('updateCurrent', Warung::class);

        $warung = $user->warung;
        abort_if($warung === null, 404);

        return $warung;
    }

    private function response(Request $request, Warung $warung): JsonResponse
    {
        return response()->json([
            'data' => (new WarungResource($warung))->resolve($request),
        ]);
    }

    private function deleteManagedLogo(?string $logoPath, Warung $warung): void
    {
        if ($logoPath !== null && str_starts_with($logoPath, 'warung-logos/'.$warung->getKey().'/')) {
            Storage::disk('public')->delete($logoPath);
        }
    }
}
