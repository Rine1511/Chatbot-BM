<?php

namespace App\Http\Requests\Admin;

use App\Models\KnowledgeDocument;
use App\Support\InputSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawSourceUrl = trim((string) $this->input('source_url', ''));

        $this->merge([
            'title' => InputSanitizer::cleanSingleLine($this->input('title')),
            'description' => InputSanitizer::cleanMultiLine($this->input('description')),
            'source_label' => InputSanitizer::cleanSingleLine($this->input('source_label')),
            'raw_content' => InputSanitizer::cleanMultiLine($this->input('raw_content')),
            'source_url' => $rawSourceUrl === '' ? null : strip_tags($rawSourceUrl),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var KnowledgeDocument|null $document */
        $document = $this->route('knowledge');
        $requiresNewFile = $this->input('source_type') === KnowledgeDocument::SOURCE_FILE
            && (! $document || ! $document->file_path);

        return [
            'category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_type' => ['required', 'string', 'in:text,file'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'string', 'url:http,https', 'max:500'],
            'raw_content' => ['required_if:source_type,text', 'nullable', 'string', 'min:20', 'max:200000'],
            'document_file' => [
                Rule::requiredIf($requiresNewFile),
                'nullable',
                'file',
                'max:10240',
                'extensions:txt,md,csv,pdf,docx',
                'mimetypes:text/plain,text/x-markdown,text/markdown,text/csv,application/csv,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip',
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori dokumen wajib dipilih.',
            'title.required' => 'Judul dokumen wajib diisi.',
            'title.min' => 'Judul dokumen minimal 3 karakter.',
            'raw_content.required_if' => 'Konten teks wajib diisi jika memilih tipe input teks.',
            'raw_content.min' => 'Konten teks minimal 20 karakter.',
            'document_file.required' => 'File dokumen wajib diunggah saat beralih ke mode unggah file.',
            'document_file.max' => 'Ukuran file maksimal 10 MB.',
            'document_file.extensions' => 'Format file yang didukung hanya TXT, MD, CSV, PDF, dan DOCX.',
            'document_file.mimetypes' => 'Tipe MIME file tidak diizinkan atau tidak sesuai format dokumen yang didukung.',
            'source_url.url' => 'Tautan sumber harus menggunakan URL http:// atau https:// yang valid.',
        ];
    }
}
