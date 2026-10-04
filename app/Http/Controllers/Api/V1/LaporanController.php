<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Laporan\BuildLaporanPeriode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LaporanPeriodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LaporanController extends Controller
{
    public function penjualan(LaporanPeriodeRequest $request, BuildLaporanPeriode $report): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $this->authorizeReportAccess($actor);
        $filters = $request->validated();

        return response()->json(['data' => $report->penjualan($actor, $filters['date_from'], $filters['date_to'])]);
    }

    public function pembelian(LaporanPeriodeRequest $request, BuildLaporanPeriode $report): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        $this->authorizeReportAccess($actor);
        $filters = $request->validated();

        return response()->json(['data' => $report->pembelian($actor, $filters['date_from'], $filters['date_to'])]);
    }

    private function authorizeReportAccess(User $actor): void
    {
        abort_unless(in_array($actor->role, ['owner', 'manager'], true) && $actor->warung_id !== null, 403);
    }
}
