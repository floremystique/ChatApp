<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class TypingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Compatibility: some clients send { typing: true|false }.
        if (!$this->has('is_typing') && $this->has('typing')) {
            $this->merge(['is_typing' => $this->input('typing')]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'is_typing' => ['required', 'boolean'],
        ];
    }
}
