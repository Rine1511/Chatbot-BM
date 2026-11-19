<?php

use App\Services\Knowledge\FaqIndexingService;
use App\Services\SibBm\SibBmSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rag:index-faqs', function (FaqIndexingService $faqIndexingService) {
    $count = $faqIndexingService->indexPendingFaqs();
    $this->info("Berhasil mengindeks {$count} FAQ aktif.");
})->purpose('Indeks FAQ aktif yang berstatus pending/failed ke penyimpanan vektor');

Artisan::command('sibbm:sync {--source=auto : Sumber data (auto|api|local)} {--no-index : Lewati proses embedding otomatis} {--prune : Nonaktifkan dokumen bangunan yang sudah dihapus di SIB-BM}', function (SibBmSyncService $syncService) {
    $source = (string) $this->option('source');
    $autoIndex = ! (bool) $this->option('no-index');
    $prune = (bool) $this->option('prune');

    $stats = $syncService->syncFromSource($source, null, $autoIndex, $prune);

    if ($stats['source'] === 'unconfigured') {
        $this->warn('SIB-BM REST API belum aktif dan berkas snapshot lokal belum tersedia.');

        return;
    }

    $this->info(sprintf(
        'SIB-BM sync [%s]: total=%d baru=%d diperbarui=%d tetap=%d terindeks=%d tertunda=%d dinonaktifkan=%d',
        $stats['source'],
        $stats['total'],
        $stats['created'],
        $stats['updated'],
        $stats['unchanged'],
        $stats['indexed'],
        $stats['index_deferred'],
        $stats['deactivated']
    ));
})->purpose('Sinkronkan data bangunan SIB-BM ke cache dan Knowledge Base chatbot');
