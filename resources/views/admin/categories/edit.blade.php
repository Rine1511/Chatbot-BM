@extends('admin.layouts.app')

@section('title', 'Ubah Kategori')
@section('header_title', 'Ubah Kategori')

@section('content')
    <div class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        <div class="mb-6 flex flex-col gap-2 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ubah Kategori: {{ $category->name }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Digunakan oleh {{ $category->faqs_count }} FAQ · {{ $category->knowledge_documents_count }} dokumen Knowledge Base
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')
            @include('admin.categories._form', ['category' => $category])

            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('admin.categories.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-5 py-2.5 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
