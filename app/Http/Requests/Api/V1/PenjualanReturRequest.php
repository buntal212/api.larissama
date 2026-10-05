<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class PenjualanReturRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'manager'], true)
            && $this->user()?->warung_id !== null;
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:1', 'max:1000', 'regex:/\S/'],
            'nominal' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/', 'gt:0'],
        ];
    }
}
