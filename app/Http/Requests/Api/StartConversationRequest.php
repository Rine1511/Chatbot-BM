<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'origin_domain' => ['nullable', 'string', 'max:255'],
            'visitor_id' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'origin_domain.max' => 'Domain asal maksimal 255 karakter.',
            'visitor_id.max' => 'Identitas pengunjung maksimal 120 karakter.',
        ];
    }
}
