<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantReadScope;
use App\Models\User;
use App\Rules\PositivePageNumber;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UserIndexRequest extends FormRequest
{
    use ValidatesTenantReadScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            ...$this->tenantReadScopeRules(),
            'page' => ['sometimes', new PositivePageNumber],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(['nama', '-nama'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
            'aktif' => ['sometimes', 'nullable', 'string', Rule::in(['true', 'false', '1', '0'])],
        ];
    }
}
