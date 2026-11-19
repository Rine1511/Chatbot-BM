<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SibBmException;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeDocument;
use App\Models\SibBmBuildingCache;
use App\Services\SibBm\SibBmClient;
use App\Services\SibBm\SibBmSyncService;
use App\Support\SecretRedactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SibBmController extends Controller
{
    public function index(SibBmClient $client): View
    {
        $buildings = SibBmBuildingCache::query()
            ->orderBy('name')
            ->paginate(15);

        $syncedDocsCount = KnowledgeDocument::query()
            ->where('source_type', KnowledgeDocument::SOURCE_SIBBM)
            ->count();

        $indexedDocsCount = KnowledgeDocument::query()
            ->where('source_type', KnowledgeDocument::SOURCE_SIBBM)
            ->where('processing_status', KnowledgeDocument::STATUS_INDEXED)
            ->count();

        return view('admin.sibbm.index', [
            'buildings' => $buildings,
            'apiEnabled' => $client->isApiConfigured(),
            'hasLocalSnapshot' => $client->hasLocalSnapshot(),
            'syncedDocsCount' => $syncedDocsCount,
            'indexedDocsCount' => $indexedDocsCount,
        ]);
    }

    public function sync(Request $request, SibBmSyncService $syncService): RedirectResponse
    {
        $source = (string) $request->input('source', 'auto');
        if (! in_array($source, ['auto', 'api', 'local'], true)) {
            $source = 'auto';
        }

        try {
            $stats = $syncService->syncFromSource(
                source: $source,
                actorId: $request->user()?->id,
                autoIndex: true,
                pruneMissing: $request->boolean('prune_missing')
            );
        } catch (SibBmException $e) {
            return redirect()
                ->route('admin.sibbm.index')
                ->withErrors(['sibbm' => SecretRedactor::redact($e->getMessage())]);
        }

        if ($stats['source'] === 'unconfigured') {
            return redirect()
                ->route('admin.sibbm.index')
                ->withErrors([
                    'sibbm' => 'REST API SIB-BM belum diaktifkan dan berkas snapshot JSON sementara belum ditemukan. Silakan impor data JSON sementara atau aktifkan SIBBM_API_ENABLED di .env.',
                ]);
        }

        return redirect()
            ->route('admin.sibbm.index')
            ->with('status', sprintf(
                'Sinkronisasi SIB-BM selesai (%s): %d data diproses (%d baru, %d diperbarui, %d tetap, %d terindeks ke vektor, %d menunggu indeks).',
                $stats['source'],
                $stats['total'],
                $stats['created'],
                $stats['updated'],
                $stats['unchanged'],
                $stats['indexed'],
                $stats['index_deferred']
            ));
    }

    public function importJson(Request $request, SibBmSyncService $syncService): RedirectResponse
    {
        $validated = $request->validate([
            'buildings_json' => ['required', 'string', 'max:500000'],
        ], [
            'buildings_json.required' => 'Payload JSON data bangunan SIB-BM wajib diisi.',
        ]);

        $decoded = json_decode($validated['buildings_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return redirect()
                ->route('admin.sibbm.index')
                ->withInput()
                ->withErrors(['buildings_json' => 'Format JSON tidak valid: '.json_last_error_msg()]);
        }

        $stats = $syncService->syncBuildings(
            rawBuildings: $decoded,
            actorId: $request->user()?->id,
            autoIndex: true,
            pruneMissing: false
        );

        if ($stats['total'] === 0) {
            return redirect()
                ->route('admin.sibbm.index')
                ->withInput()
                ->withErrors(['buildings_json' => 'Tidak ada data bangunan valid yang ditemukan pada JSON (minimal butuh field external_id/id dan name/nama_gedung).']);
        }

        return redirect()
            ->route('admin.sibbm.index')
            ->with('status', sprintf(
                'Impor sementara SIB-BM berhasil: %d gedung diproses (%d baru, %d diperbarui, %d terindeks).',
                $stats['total'],
                $stats['created'],
                $stats['updated'],
                $stats['indexed']
            ));
    }
}
