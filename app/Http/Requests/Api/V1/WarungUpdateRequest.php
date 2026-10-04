<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class WarungUpdateRequest extends FormRequest
{
    private ?Warung $targetWarung = null;

    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->can('viewAny', Warung::class)) {
            return false;
        }

        $this->targetWarung = Warung::query()->findOrFail($this->route('id'));

        return $actor->can('update', $this->targetWarung);
    }

    public function rules(): array
    {
        return [
            'kode' => ['sometimes', 'required', 'string', 'min:1', 'max:30', Rule::unique('warungs', 'kode')->ignore($this->targetWarung)],
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'telepon' => ['sometimes', 'nullable', 'string', 'min:1', 'max:30'],
            'tanggal_mulai' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'tanggal_berakhir' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->all() === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');

                return;
            }

            if ($validator->errors()->has('tanggal_mulai') || $validator->errors()->has('tanggal_berakhir')) {
                return;
            }

            $startDate = $this->input('tanggal_mulai', $this->targetWarung?->tanggal_mulai?->toDateString());
            $endDate = $this->input('tanggal_berakhir', $this->targetWarung?->tanggal_berakhir?->toDateString());

            if (is_string($startDate) && is_string($endDate) && $endDate < $startDate) {
                $validator->errors()->add('tanggal_berakhir', 'Tanggal akhir harus sama atau sesudah tanggal mulai.');
            }
        }];
    }
}
