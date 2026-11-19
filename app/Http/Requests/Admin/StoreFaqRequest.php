<?php

namespace App\Http\Requests\Admin;

use App\Support\InputSanitizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawSourceUrl = trim((string) $this->input('source_url', ''));

        $this->merge([
            'question' => InputSanitizer::cleanMultiLine($this->input('question')),
            'answer' => InputSanitizer::cleanMultiLine($this->input('answer')),
            'keywords' => InputSanitizer::cleanSingleLine($this->input('keywords')),
            'question_variations' => InputSanitizer::cleanMultiLine($this->input('question_variations')),
            'source_url' => $rawSourceUrl === '' ? null : strip_tags($rawSourceUrl),
            'is_featured' => $this->boolean('is_featured'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'min:5', 'max:2000'],
            'answer' => ['required', 'string', 'min:5', 'max:20000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'question_variations' => ['nullable', 'string', 'max:4000'],
            'source_url' => ['nullable', 'string', 'url:http,https', 'max:500'],
            'is_featured' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori FAQ wajib dipilih.',
            'category_id.exists' => 'Kategori FAQ tidak valid.',
            'question.required' => 'Pertanyaan wajib diisi.',
            'question.min' => 'Pertanyaan minimal 5 karakter.',
            'answer.required' => 'Jawaban resmi wajib diisi.',
            'answer.min' => 'Jawaban minimal 5 karakter.',
            'source_url.url' => 'Tautan sumber harus menggunakan URL http:// atau https:// yang valid.',
        ];
    }
}
