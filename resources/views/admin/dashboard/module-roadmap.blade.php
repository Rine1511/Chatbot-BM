@extends('admin.layouts.app')

@section('title', $moduleInfo['title'])
@section('header_title', $moduleInfo['title'])

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-8">
        <div class="max-w-2xl">
            <p class="text-xs font-medium text-[#A67C16]">
                Jadwal Implementasi · {{ $moduleInfo['stage'] }}
            </p>
            <h2 class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                {{ $moduleInfo['title'] }}
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                {{ $moduleInfo['description'] }}
            </p>
            <p class="mt-4 text-xs text-slate-500">
                Struktur database dan relasi untuk modul ini telah disiapkan pada Tahap 2. Implementasi antarmuka dan layanan penuh akan dikerjakan pada {{ $moduleInfo['stage'] }} sesuai persetujuan bertahap.
            </p>
            <div class="mt-6">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center rounded-lg bg-[#0B3B27] px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-[#0E4B32]">
                    Kembali ke Dashboard Utama
                </a>
            </div>
        </div>
    </div>
@endsection
