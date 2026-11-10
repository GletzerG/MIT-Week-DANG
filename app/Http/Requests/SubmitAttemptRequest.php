<?php

namespace App\Http\Requests;

use App\Enums\QuestVerdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'quest_id'    => ['required', 'string', 'size:26'], // ULID
            'verdict'     => ['required', 'string', Rule::in(array_column(QuestVerdict::cases(), 'value'))],
            'response_ms' => ['nullable', 'integer', 'min:0', 'max:600000'],
        ];
    }

    public function messages(): array
    {
        return [
            'quest_id.size' => 'Quest tidak valid.',
            'verdict.in'    => 'Pilihan harus "fact" atau "hoax".',
        ];
    }
}
