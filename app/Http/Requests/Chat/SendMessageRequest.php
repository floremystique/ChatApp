<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'reply_to_id' => ['nullable', 'integer'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
