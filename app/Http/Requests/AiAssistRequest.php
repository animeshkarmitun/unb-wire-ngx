<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiAssistRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'text' => ['nullable','string','max:20000'],
            'story_id' => ['nullable','integer','exists:stories,id'],
            'headline' => ['nullable','string','max:500'],
            'brief' => ['nullable','string','max:1000'],
        ];
    }
}
