<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk Admin — Asisten Virtual Bahrul Maghfiroh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="min-h-screen bg-[#F7F9F8] text-slate-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
            <!-- Brand Header -->
            <div class="mb-7">
                <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg bg-[#0B3B27] text-sm font-bold text-[#D4AF37]">
                    BM
                </div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">
                    Panel Admin Asisten Virtual
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Pondok Pesantren Bahrul Maghfiroh Kota Malang
                </p>
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">
                        Alamat Email
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="admin@bahrulmaghfirohmalang.or.id"
                        class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">
                        Kata Sandi
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#0E4B32]/20"
                    >
                </div>

                <div class="flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            {{ old('remember') ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-slate-300 text-[#0E4B32] focus:ring-[#0E4B32]"
                        >
                        <span>Ingat sesi saya</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-lg bg-[#0B3B27] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#0E4B32] focus:outline-none focus:ring-2 focus:ring-[#C49A2A] focus:ring-offset-2 cursor-pointer"
                >
                    Masuk ke Dashboard
                </button>
            </form>
        </div>

        <p class="mt-5 text-center text-xs text-slate-500">
            Akses khusus pengelola resmi Pondok Pesantren Bahrul Maghfiroh.
        </p>
    </div>
</body>
</html>
