<?php

namespace App\Http\Requests;

use App\Services\RbacService;
use Illuminate\Foundation\Http\FormRequest;

class AiAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return app(RbacService::class)->can($user, 'ai', 'read');
    }

    public function rules(): array
    {
        return [
            'text' => ['nullable', 'string', 'max:20000'],
            'story_id' => ['nullable', 'integer', 'exists:stories,id'],
            'headline' => ['nullable', 'string', 'max:500'],
            'brief' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
