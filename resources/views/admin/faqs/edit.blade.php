@extends('admin.layouts.app')

@section('title', 'Ubah FAQ')
@section('header_title', 'Ubah FAQ')

@section('content')
    <div class="mx-auto max-w-4xl rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
        <div class="mb-6 flex flex-col gap-2 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Ubah FAQ #{{ $faq->id }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Pembaruan FAQ akan menandai status indeks menjadi <span class="font-mono font-medium">pending</span> agar disinkronkan ulang pada Tahap 4.
                </p>
            </div>
            <span class="font-mono text-xs text-slate-500">
                Status saat ini: {{ $faq->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.faqs.update', $faq) }}">
            @csrf
            @method('PUT')
            @include('admin.faqs._form', ['faq' => $faq])

            <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('admin.faqs.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="rounded-lg bg-[#0B3B27] px-5 py-2.5 text-xs font-semibold text-white hover:bg-[#0E4B32] cursor-pointer">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
@endsection
