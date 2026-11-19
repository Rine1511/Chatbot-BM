@extends('admin.layouts.app')

@section('title', 'Ubah Dokumen Knowledge Base')
@section('header_title', 'Ubah Dokumen Knowledge Base')

@section('content')
    <div class="mx-auto max-w-4xl rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        <div class="mb-6 flex flex-col gap-2 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ubah Dokumen: {{ $document->title }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Pembaruan dokumen akan mengatur ulang status pemrosesan menjadi <span class="font-mono font-medium">pending</span> agar diindeks ulang pada Tahap 4.
                </p>
            </div>
            <a href="{{ route('admin.knowledge.show', $document) }}" class="text-xs font-semibold text-[#0E4B32] hover:underline">
                Lihat Detail
            </a>
        </div>

        <form method="POST" action="{{ route('admin.knowledge.update', $document) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.knowledge._form', ['document' => $document])

            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('admin.knowledge.show', $document) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-5 py-2.5 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
