<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $headerToken = trim((string) $this->header('X-Widget-Session', ''));
        $bodyToken = trim((string) $this->input('session_token', ''));

        $rawMessage = (string) $this->input('message', '');
        $cleanMessage = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $rawMessage) ?? $rawMessage;

        $this->merge([
            'session_token' => $bodyToken !== '' ? $bodyToken : $headerToken,
            'message' => trim($cleanMessage),
            'category_slug' => $this->filled('category_slug')
                ? trim((string) $this->input('category_slug'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'category_slug' => ['nullable', 'string', 'max:120', 'alpha_dash'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_token.required' => 'Token sesi percakapan (session_token) wajib disertakan.',
            'session_token.size' => 'Format token sesi percakapan tidak valid.',
            'session_token.regex' => 'Format token sesi percakapan tidak valid.',
            'message.required' => 'Pesan pertanyaan tidak boleh kosong.',
            'message.min' => 'Pesan pertanyaan minimal terdiri dari 2 karakter.',
            'message.max' => 'Pesan pertanyaan maksimal 1.000 karakter.',
            'category_slug.alpha_dash' => 'Format slug kategori tidak valid.',
        ];
    }
}
