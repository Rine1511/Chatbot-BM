<div class="space-y-5">
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">
                Nama Kategori <span class="text-red-600">*</span>
            </label>
            <input
                id="name"
                name="name"
                type="text"
                required
                maxlength="120"
                value="{{ old('name', $category->name ?? '') }}"
                placeholder="Contoh: Kegiatan"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>

        <div>
            <label for="slug" class="block text-sm font-medium text-slate-700">
                Slug (Otomatis jika dikosongkan)
            </label>
            <input
                id="slug"
                name="slug"
                type="text"
                maxlength="120"
                value="{{ old('slug', $category->slug ?? '') }}"
                placeholder="contoh-slug-kategori"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-slate-700">
            Deskripsi Kategori
        </label>
        <textarea
            id="description"
            name="description"
            rows="3"
            maxlength="1000"
            placeholder="Jelaskan cakupan topik FAQ dan dokumen dalam kategori ini..."
            class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
        >{{ old('description', $category->description ?? '') }}</textarea>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="sort_order" class="block text-sm font-medium text-slate-700">
                Urutan Tampil
            </label>
            <input
                id="sort_order"
                name="sort_order"
                type="number"
                min="0"
                max="9999"
                required
                value="{{ old('sort_order', $category->sort_order ?? $nextOrder ?? 0) }}"
                class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3.5 py-2.5 font-mono text-sm text-slate-900 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
            >
        </div>

        <div class="flex items-end pb-2">
            <label class="inline-flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}
                    class="h-4 w-4 rounded border-slate-300 text-[#0E4B32] focus:ring-[#0E4B32]"
                >
                <span>Kategori aktif digunakan</span>
            </label>
        </div>
    </div>
</div>
