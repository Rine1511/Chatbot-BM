@extends('admin.layouts.app')

@section('title', 'Tambah Dokumen Knowledge Base')
@section('header_title', 'Tambah Dokumen Knowledge Base')

@section('content')
    <div class="mx-auto max-w-4xl rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        <div class="mb-6 border-b border-slate-200 pb-4">
            <h2 class="text-lg font-bold text-slate-900">Tambah Dokumen Knowledge Base</h2>
            <p class="mt-1 text-sm text-slate-500">
                Tambahkan materi berbasis teks atau unggah dokumen resmi pondok untuk diolah pada Tahap 4 (chunking &amp; vector embedding).
            </p>
        </div>

        <form method="POST" action="{{ route('admin.knowledge.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.knowledge._form')

            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('admin.knowledge.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-5 py-2.5 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer">
                    Simpan Dokumen
                </button>
            </div>
        </form>
    </div>
@endsection
