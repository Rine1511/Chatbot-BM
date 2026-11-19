<?php

namespace Tests\Unit;

use App\Exceptions\DocumentExtractionException;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\DocumentExtractor;
use App\Services\Knowledge\TextChunker;
use App\Services\Knowledge\TextNormalizer;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class DocumentExtractionAndChunkingTest extends TestCase
{
    public function test_normalizer_cleans_whitespace_and_estimates_tokens(): void
    {
        $normalizer = new TextNormalizer;

        $input = "\xEF\xBB\xBF  Baris pertama\r\n\r\n\r\n\r\nBaris   kedua\t\tdengan   spasi  \x07";
        $normalized = $normalizer->normalize($input);

        $this->assertSame("Baris pertama\n\nBaris kedua dengan spasi", $normalized);
        $this->assertGreaterThan(0, $normalizer->estimateTokens($normalized));
        $this->assertSame(0, $normalizer->estimateTokens('   '));
    }

    public function test_extractor_handles_text_txt_md_csv_docx_and_pdf(): void
    {
        Storage::fake('local');
        $extractor = new DocumentExtractor(new TextNormalizer);

        // 1. Direct text
        $textDoc = new KnowledgeDocument([
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => "Profil Pondok Pesantren Bahrul Maghfiroh.\r\n\r\nBerlokasi di Kota Malang.",
        ]);
        $this->assertSame(
            "Profil Pondok Pesantren Bahrul Maghfiroh.\n\nBerlokasi di Kota Malang.",
            $extractor->extract($textDoc)
        );

        // 2. TXT & MD
        Storage::disk('local')->put('knowledge_docs/uji.md', "# Judul Materi\n\nIni adalah isi dokumen markdown pondok.");
        $mdDoc = new KnowledgeDocument([
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'original_filename' => 'uji.md',
            'file_path' => 'knowledge_docs/uji.md',
        ]);
        $this->assertStringContainsString('# Judul Materi', $extractor->extract($mdDoc));

        // 3. CSV (semicolon delimiter)
        Storage::disk('local')->put('knowledge_docs/gedung.csv', "Nama Gedung;Fungsi\nGedung Utama;Pusat Administrasi dan Aula\nAsrama Putra;Hunian Santri");
        $csvDoc = new KnowledgeDocument([
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'original_filename' => 'gedung.csv',
            'file_path' => 'knowledge_docs/gedung.csv',
        ]);
        $csvExtracted = $extractor->extract($csvDoc);
        $this->assertStringContainsString('Nama Gedung: Gedung Utama | Fungsi: Pusat Administrasi dan Aula', $csvExtracted);
        $this->assertStringContainsString('Nama Gedung: Asrama Putra | Fungsi: Hunian Santri', $csvExtracted);

        // 4. DOCX (real ZipArchive with word/document.xml)
        $docxPath = Storage::disk('local')->path('knowledge_docs/panduan.docx');
        @mkdir(dirname($docxPath), 0777, true);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
                <w:body>
                    <w:p><w:r><w:t>Panduan Pendaftaran Santri Baru</w:t></w:r></w:p>
                    <w:p><w:r><w:t>Silakan melengkapi berkas administrasi di sekretariat.</w:t></w:r></w:p>
                </w:body>
            </w:document>');
        $zip->close();

        $docxDoc = new KnowledgeDocument([
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'original_filename' => 'panduan.docx',
            'file_path' => 'knowledge_docs/panduan.docx',
        ]);
        $docxExtracted = $extractor->extract($docxDoc);
        $this->assertStringContainsString('Panduan Pendaftaran Santri Baru', $docxExtracted);
        $this->assertStringContainsString('Silakan melengkapi berkas administrasi', $docxExtracted);

        // 5. PDF (minimal valid PDF with BT/ET text stream)
        $pdfPath = Storage::disk('local')->path('knowledge_docs/info.pdf');
        file_put_contents($pdfPath, $this->buildMinimalPdf('Informasi Pondok Bahrul Maghfiroh Malang'));
        $pdfDoc = new KnowledgeDocument([
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'original_filename' => 'info.pdf',
            'file_path' => 'knowledge_docs/info.pdf',
        ]);
        $pdfExtracted = $extractor->extract($pdfDoc);
        $this->assertStringContainsString('Informasi Pondok Bahrul Maghfiroh Malang', $pdfExtracted);
    }

    public function test_extractor_rejects_empty_missing_and_corrupt_documents(): void
    {
        Storage::fake('local');
        $extractor = new DocumentExtractor(new TextNormalizer);

        // Empty text
        try {
            $extractor->extract(new KnowledgeDocument([
                'source_type' => KnowledgeDocument::SOURCE_TEXT,
                'raw_content' => '   ',
            ]));
            $this->fail('Seharusnya melempar DocumentExtractionException untuk teks kosong.');
        } catch (DocumentExtractionException $e) {
            $this->assertStringContainsString('kosong', $e->getMessage());
        }

        // Missing file
        try {
            $extractor->extract(new KnowledgeDocument([
                'source_type' => KnowledgeDocument::SOURCE_FILE,
                'original_filename' => 'tidak-ada.txt',
                'file_path' => 'knowledge_docs/tidak-ada.txt',
            ]));
            $this->fail('Seharusnya melempar DocumentExtractionException untuk file hilang.');
        } catch (DocumentExtractionException $e) {
            $this->assertStringContainsString('tidak ditemukan', $e->getMessage());
        }

        // Corrupt DOCX
        Storage::disk('local')->put('knowledge_docs/rusak.docx', 'bukan-zip-valid');
        $this->expectException(DocumentExtractionException::class);
        $extractor->extract(new KnowledgeDocument([
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'original_filename' => 'rusak.docx',
            'file_path' => 'knowledge_docs/rusak.docx',
        ]));
    }

    public function test_chunker_splits_text_with_configurable_size_and_overlap(): void
    {
        $chunker = new TextChunker(new TextNormalizer);

        $paragraphA = str_repeat('Kalimat informasi pendidikan diniyah pondok pesantren. ', 4);
        $paragraphB = str_repeat('Kalimat informasi asrama dan fasilitas santri putra putri. ', 4);
        $text = trim($paragraphA)."\n\n".trim($paragraphB);

        $chunks = $chunker->chunk($text, 180, 40);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $idx => $chunk) {
            $this->assertSame($idx, $chunk['chunk_index']);
            $this->assertNotEmpty($chunk['content']);
            $this->assertSame(64, strlen($chunk['content_hash']));
            $this->assertGreaterThan(0, $chunk['token_count']);
            $this->assertLessThanOrEqual(220, mb_strlen($chunk['content']));
        }

        // Pastikan seluruh kalimat akhir tetap muncul pada chunk terakhir
        $lastChunk = end($chunks);
        $this->assertStringContainsString('fasilitas santri putra putri', $lastChunk['content']);
    }

    public function test_chunker_validates_invalid_size_and_overlap(): void
    {
        $chunker = new TextChunker(new TextNormalizer);

        $this->expectException(InvalidArgumentException::class);
        $chunker->chunk('Teks panjang untuk uji validasi ukuran chunk.', 100, 100);
    }

    private function buildMinimalPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT\n/F1 12 Tf\n72 720 Td\n({$escaped}) Tj\nET";
        $streamLen = strlen($stream);

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n",
            "4 0 obj\n<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
