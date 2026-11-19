@php
    $currentSourceType = old('source_type', $document->source_type ?? \App\Models\KnowledgeDocument::SOURCE_TEXT);
    if (! in_array($currentSourceType, [\App\Models\KnowledgeDocument::SOURCE_TEXT, \App\Models\KnowledgeDocument::SOURCE_FILE], true)) {
        $currentSourceType = \App\Models\KnowledgeDocument::SOURCE_TEXT;
    }
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="category_id" class="block text-sm font-medium text-slate-700">
                Kategori Pengetahuan <span class="text-red-600">*</span>
            </label>
            <select
                id="category_id"
                name="category_id"
                required
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
                <option value="">-- Pilih Kategori --</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" {{ (int) old('category_id', $document->category_id ?? 0) === $category->id ? 'selected' : '' }}>
                        {{ $category->name }}{{ $category->is_active ? '' : ' (Nonaktif)' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="source_label" class="block text-sm font-medium text-slate-700">
                Label / Nama Sumber Resmi
            </label>
            <input
                id="source_label"
                name="source_label"
                type="text"
                maxlength="255"
                value="{{ old('source_label', $document->source_label ?? '') }}"
                placeholder="Contoh: Panduan PSB 2026 / Brosur Resmi Pondok"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="title" class="block text-sm font-medium text-slate-700">
                Judul Dokumen <span class="text-red-600">*</span>
            </label>
            <input
                id="title"
                name="title"
                type="text"
                required
                maxlength="255"
                value="{{ old('title', $document->title ?? '') }}"
                placeholder="Contoh: Panduan Pendaftaran Santri Baru"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>

        <div>
            <label for="source_url" class="block text-sm font-medium text-slate-700">
                Tautan Referensi (Opsional)
            </label>
            <input
                id="source_url"
                name="source_url"
                type="url"
                maxlength="500"
                value="{{ old('source_url', $document->source_url ?? '') }}"
                placeholder="https://bahrulmaghfirohmalang.or.id/..."
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-slate-700">
            Deskripsi Singkat Dokumen
        </label>
        <textarea
            id="description"
            name="description"
            rows="2"
            maxlength="2000"
            placeholder="Ringkasan isi atau konteks dokumen untuk memudahkan pengelolaan admin..."
            class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
        >{{ old('description', $document->description ?? '') }}</textarea>
    </div>

    <!-- Source Type Selector -->
    <div>
        <span class="block text-sm font-medium text-slate-700">
            Metode Input Konten <span class="text-red-600">*</span>
        </span>
        <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3.5 cursor-pointer hover:border-[#0E4B32]">
                <input
                    type="radio"
                    name="source_type"
                    value="text"
                    {{ $currentSourceType === 'text' ? 'checked' : '' }}
                    class="mt-0.5 h-4 w-4 text-[#0E4B32] focus:ring-[#0E4B32]"
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Input Konten Teks Langsung</span>
                    <span class="block text-xs text-slate-500">Tempel atau ketik materi pengetahuan resmi secara langsung.</span>
                </span>
            </label>

            <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-3.5 cursor-pointer hover:border-[#0E4B32]">
                <input
                    type="radio"
                    name="source_type"
                    value="file"
                    {{ $currentSourceType === 'file' ? 'checked' : '' }}
                    class="mt-0.5 h-4 w-4 text-[#0E4B32] focus:ring-[#0E4B32]"
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-900">Unggah File Dokumen</span>
                    <span class="block text-xs text-slate-500">Format: TXT, MD, CSV, PDF, atau DOCX (maks. 10 MB).</span>
                </span>
            </label>
        </div>
    </div>

    <!-- Text Content Section -->
    <div id="kb-text-section" class="{{ $currentSourceType === 'text' ? '' : 'hidden' }}">
        <label for="raw_content" class="block text-sm font-medium text-slate-700">
            Konten Teks Knowledge Base <span class="text-red-600">*</span>
        </label>
        <textarea
            id="raw_content"
            name="raw_content"
            rows="10"
            maxlength="200000"
            placeholder="Masukkan teks dokumen resmi yang akan dipecah menjadi potongan teks (chunks) dan diindeks pada Tahap 4..."
            class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 font-mono text-xs leading-relaxed text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
        >{{ old('raw_content', $document->raw_content ?? '') }}</textarea>
        <p class="mt-1 text-xs text-slate-500">Minimal 20 karakter. Teks disimpan bersih sebagai sumber Tahap 4 (chunking &amp; embedding).</p>
    </div>

    <!-- File Upload Section -->
    <div id="kb-file-section" class="{{ $currentSourceType === 'file' ? '' : 'hidden' }} space-y-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-5">
        @if (isset($document) && $document->file_path)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 px-4 py-3 text-xs text-emerald-950">
                <p class="font-semibold">Berkas tersimpan saat ini:</p>
                <p class="mt-0.5 font-mono">{{ $document->original_filename }} ({{ $document->formattedFileSize() }})</p>
                <p class="mt-1 text-emerald-800">Biarkan input unggahan kosong jika tidak ingin mengganti berkas yang sudah ada.</p>
            </div>
        @endif

        <div>
            <label for="document_file" class="block text-sm font-medium text-slate-700">
                Pilih File Dokumen {{ isset($document) && $document->file_path ? '(Opsional untuk mengganti)' : '*' }}
            </label>
            <input
                id="document_file"
                name="document_file"
                type="file"
                accept=".txt,.md,.csv,.pdf,.docx"
                class="mt-2 block w-full text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-[#0B3B27] file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-[#0E4B32] cursor-pointer"
            >
            <p class="mt-1.5 text-xs text-slate-500">
                Format yang didukung: <span class="font-mono">.txt, .md, .csv, .pdf, .docx</span> · Maksimal 10 MB · Disimpan pada storage privat server.
            </p>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <label class="inline-flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                {{ old('is_active', $document->is_active ?? true) ? 'checked' : '' }}
                class="h-4 w-4 rounded border-slate-300 text-[#0E4B32] focus:ring-[#0E4B32]"
            >
            <span>
                <span class="font-medium text-slate-900">Aktifkan Dokumen</span>
                <span class="block text-xs text-slate-500">Dokumen nonaktif tidak akan diindeks atau dipakai oleh mesin pencari pengetahuan chatbot.</span>
            </span>
        </label>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const radios = document.querySelectorAll('input[name="source_type"]');
        const textSection = document.getElementById('kb-text-section');
        const fileSection = document.getElementById('kb-file-section');

        function syncSourceSections() {
            const selected = document.querySelector('input[name="source_type"]:checked')?.value || 'text';
            if (selected === 'file') {
                textSection?.classList.add('hidden');
                fileSection?.classList.remove('hidden');
            } else {
                fileSection?.classList.add('hidden');
                textSection?.classList.remove('hidden');
            }
        }

        radios.forEach((radio) => radio.addEventListener('change', syncSourceSections));
        syncSourceSections();
    })();
</script>
@endpush
