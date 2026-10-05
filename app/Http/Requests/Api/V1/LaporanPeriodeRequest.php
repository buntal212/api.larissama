<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\LocalPeriodUtcMysqlRange;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class LaporanPeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'manager'], true)
            && $this->user()?->warung_id !== null;
    }

    public function rules(): array
    {
        $timezone = $this->user()?->warung?->timezone;

        return [
            'date_from' => ['required', 'date_format:Y-m-d', new LocalPeriodUtcMysqlRange($timezone, 'start')],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', new LocalPeriodUtcMysqlRange($timezone, 'end')],
        ];
    }
}
