<?php

namespace App\Http\Requests;

use App\Services\RbacService;
use Illuminate\Foundation\Http\FormRequest;

class TusCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return app(RbacService::class)->can($user, 'media', 'create');
    }

    public function rules(): array
    {
        return [
            'upload_length' => ['required', 'integer', 'min:1', 'max:104857600'],
            'metadata' => ['nullable', 'string', 'max:2048'],
            'kind' => ['nullable', 'string', 'in:photo,video,document,audio'],
            'filename' => ['nullable', 'string', 'max:255', 'regex:/\.(jpe?g|png|webp|gif|mp4|mov|pdf|docx?|bin|txt)$/i'],
        ];
    }
}
