<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTransactionWriteScope;
use App\Models\Pembelian;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;

abstract class PembelianKoreksiRequest extends FormRequest
{
    use ValidatesTransactionWriteScope;

    protected function authorizePurchaseCorrection(string $ability): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! ($actor->role === 'superadmin' && $actor->warung_id === null)
            && (! in_array($actor->role, ['owner', 'manager'], true) || $actor->warung_id === null)) {
            return false;
        }

        if ($actor->role === 'superadmin' && $actor->warung_id === null) {
            return true;
        }

        $purchase = Pembelian::query()
            ->where('warung_id', $actor->warung_id)
            ->find($this->route('id'));

        if (! $purchase instanceof Pembelian) {
            throw (new ModelNotFoundException)->setModel(Pembelian::class, [$this->route('id')]);
        }

        return $actor->can($ability, $purchase);
    }

    /** @return array<int, string> */
    protected function correctionReasonRules(): array
    {
        return ['required', 'string', 'min:1', 'max:1000', 'not_regex:/\A\s*\z/u'];
    }
}
