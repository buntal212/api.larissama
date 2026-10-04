<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Pembelian;
use App\Rules\PositivePageNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class PembelianIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Pembelian::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', new PositivePageNumber],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['-tanggal', 'tanggal'])],
            'date_from' => ['required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
