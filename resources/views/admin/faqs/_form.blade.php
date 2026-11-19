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
                    <option value="{{ $category->id }}" {{ (int) old('category_id', $faq->category_id ?? 0) === $category->id ? 'selected' : '' }}>
                        {{ $category->name }}{{ $category->is_active ? '' : ' (Nonaktif)' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="source_url" class="block text-sm font-medium text-slate-700">
                Tautan Sumber Resmi (Opsional)
            </label>
            <input
                id="source_url"
                name="source_url"
                type="url"
                maxlength="500"
                value="{{ old('source_url', $faq->source_url ?? '') }}"
                placeholder="https://bahrulmaghfirohmalang.or.id/..."
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>
    </div>

    <div>
        <label for="question" class="block text-sm font-medium text-slate-700">
            Pertanyaan Utama <span class="text-red-600">*</span>
        </label>
        <input
            id="question"
            name="question"
            type="text"
            required
            maxlength="2000"
            value="{{ old('question', $faq->question ?? '') }}"
            placeholder="Contoh: Bagaimana alur pendaftaran santri baru di Pondok Pesantren Bahrul Maghfiroh?"
            class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
        >
    </div>

    <div>
        <label for="answer" class="block text-sm font-medium text-slate-700">
            Jawaban Resmi <span class="text-red-600">*</span>
        </label>
        <textarea
            id="answer"
            name="answer"
            rows="6"
            required
            maxlength="20000"
            placeholder="Tuliskan jawaban resmi yang akurat, jelas, dan mudah dipahami pengunjung website..."
            class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm leading-relaxed text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
        >{{ old('answer', $faq->answer ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="keywords" class="block text-sm font-medium text-slate-700">
                Kata Kunci Pencarian (Dipisahkan Koma)
            </label>
            <input
                id="keywords"
                name="keywords"
                type="text"
                maxlength="500"
                value="{{ old('keywords', $faq->keywords ?? '') }}"
                placeholder="Contoh: pendaftaran, PSB, santri baru, formulir"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
            <p class="mt-1 text-xs text-slate-500">Membantu pencarian kata kunci di dashboard maupun pencocokan di tahap RAG.</p>
        </div>

        <div>
            <label for="question_variations" class="block text-sm font-medium text-slate-700">
                Variasi Pertanyaan (Satu Baris Per Variasi)
            </label>
            <textarea
                id="question_variations"
                name="question_variations"
                rows="3"
                maxlength="4000"
                placeholder="Cara daftar santri baru&#10;Syarat masuk Pondok Bahrul Maghfiroh&#10;Kapan jadwal pendaftaran dibuka"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >{{ old('question_variations', $faq->question_variations ?? '') }}</textarea>
        </div>
    </div>

    <div class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
        <label class="inline-flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                {{ old('is_active', $faq->is_active ?? true) ? 'checked' : '' }}
                class="h-4 w-4 rounded border-slate-300 text-[#0E4B32] focus:ring-[#0E4B32]"
            >
            <span>
                <span class="font-medium text-slate-900">Aktifkan FAQ</span>
                <span class="block text-xs text-slate-500">FAQ nonaktif tidak akan pernah digunakan sebagai sumber jawaban chatbot.</span>
            </span>
        </label>

        <label class="inline-flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
            <input
                type="checkbox"
                name="is_featured"
                value="1"
                {{ old('is_featured', $faq->is_featured ?? false) ? 'checked' : '' }}
                class="h-4 w-4 rounded border-slate-300 text-[#C49A2A] focus:ring-[#C49A2A]"
            >
            <span>
                <span class="font-medium text-slate-900">Tandai Prioritas Utama</span>
                <span class="block text-xs text-slate-500">Menandai FAQ populer yang sering ditanyakan pengunjung.</span>
            </span>
        </label>
    </div>
</div>
