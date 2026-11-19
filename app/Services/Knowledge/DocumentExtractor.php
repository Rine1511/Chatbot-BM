<?php

namespace App\Services\Knowledge;

use App\Exceptions\DocumentExtractionException;
use App\Models\KnowledgeDocument;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

class DocumentExtractor
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Ekstrak dan normalisasi konten teks dari KnowledgeDocument.
     *
     * @throws DocumentExtractionException
     */
    public function extract(KnowledgeDocument $document): string
    {
        if ($document->source_type === KnowledgeDocument::SOURCE_FILE) {
            $rawText = $this->extractFromFile($document);
        } else {
            $rawText = (string) ($document->raw_content ?? '');
        }

        $normalized = $this->normalizer->normalize($rawText);

        if ($normalized === '' || mb_strlen($normalized) < 10) {
            throw new DocumentExtractionException(
                'Konten dokumen kosong atau terlalu pendek setelah ekstraksi dan normalisasi (minimal 10 karakter bermakna).'
            );
        }

        return $normalized;
    }

    private function extractFromFile(KnowledgeDocument $document): string
    {
        if (! $document->file_path) {
            throw new DocumentExtractionException('Path berkas dokumen tidak ditemukan.');
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($document->file_path)) {
            throw new DocumentExtractionException('Berkas dokumen tidak ditemukan di penyimpanan server.');
        }

        $extension = strtolower(pathinfo(
            $document->original_filename ?: $document->file_path,
            PATHINFO_EXTENSION
        ));

        $absolutePath = $disk->path($document->file_path);

        return match ($extension) {
            'txt', 'md' => $this->readPlainText($absolutePath),
            'csv' => $this->readCsv($absolutePath),
            'pdf' => $this->readPdf($absolutePath),
            'docx' => $this->readDocx($absolutePath),
            default => throw new DocumentExtractionException("Format berkas .{$extension} tidak didukung untuk ekstraksi."),
        };
    }

    private function readPlainText(string $path): string
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new DocumentExtractionException('Gagal membaca berkas teks.');
        }

        return $content;
    }

    private function readCsv(string $path): string
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new DocumentExtractionException('Gagal membaca berkas CSV.');
        }

        $normalizedRaw = $this->normalizer->normalize($raw);
        if ($normalizedRaw === '') {
            return '';
        }

        $lines = explode("\n", $normalizedRaw);
        $firstLine = $lines[0] ?? '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        if ($rows === []) {
            return '';
        }

        $headers = array_map(static fn ($h) => trim((string) $h), $rows[0]);
        $hasHeader = count($rows) > 1 && count(array_filter($headers, fn ($h) => $h !== '')) > 0;

        $outputLines = [];
        $startIndex = $hasHeader ? 1 : 0;

        for ($i = $startIndex; $i < count($rows); $i++) {
            $row = $rows[$i];
            $parts = [];

            foreach ($row as $colIndex => $value) {
                $val = trim((string) $value);
                if ($val === '') {
                    continue;
                }

                if ($hasHeader && isset($headers[$colIndex]) && $headers[$colIndex] !== '') {
                    $parts[] = "{$headers[$colIndex]}: {$val}";
                } else {
                    $parts[] = $val;
                }
            }

            if ($parts !== []) {
                $outputLines[] = implode(' | ', $parts);
            }
        }

        // Jika hanya ada 1 baris CSV (misal tanpa baris data tambahan), kembalikan baris tersebut
        if ($outputLines === [] && $rows !== []) {
            return implode(' | ', array_filter(array_map(static fn ($v) => trim((string) $v), $rows[0])));
        }

        return implode("\n", $outputLines);
    }

    private function readPdf(string $path): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
        } catch (Throwable $e) {
            throw new DocumentExtractionException('Berkas PDF gagal dibaca atau format PDF tidak valid: '.$e->getMessage(), 0, $e);
        }

        return (string) $text;
    }

    private function readDocx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new DocumentExtractionException('Ekstensi PHP ZipArchive tidak tersedia untuk membaca DOCX.');
        }

        $zip = new ZipArchive;
        $opened = $zip->open($path);
        if ($opened !== true) {
            throw new DocumentExtractionException('Berkas DOCX rusak atau bukan arsip ZIP yang valid.');
        }

        try {
            $documentXml = $zip->getFromName('word/document.xml');
            if ($documentXml === false) {
                throw new DocumentExtractionException('Struktur DOCX tidak valid (word/document.xml tidak ditemukan).');
            }

            $sections = [];
            $sections[] = $this->extractWordXmlText($documentXml);

            // Sertakan header/footer jika berisi informasi penting
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $entryName = is_array($stat) ? ($stat['name'] ?? '') : '';
                if (preg_match('/^word\/(header|footer)\d*\.xml$/', $entryName) === 1) {
                    $extraXml = $zip->getFromIndex($i);
                    if (is_string($extraXml) && $extraXml !== '') {
                        $extraText = $this->extractWordXmlText($extraXml);
                        if ($extraText !== '') {
                            $sections[] = $extraText;
                        }
                    }
                }
            }

            return implode("\n\n", array_filter($sections, fn ($s) => trim($s) !== ''));
        } finally {
            $zip->close();
        }
    }

    private function extractWordXmlText(string $xmlContent): string
    {
        $dom = new DOMDocument;
        $loaded = @$dom->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $loaded) {
            throw new DocumentExtractionException('Gagal menguraikan XML di dalam berkas DOCX.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xpath->query('//w:p');
        if ($paragraphs === false) {
            return '';
        }

        $lines = [];
        foreach ($paragraphs as $paragraph) {
            $textNodes = $xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph);
            if ($textNodes === false) {
                continue;
            }

            $buffer = '';
            foreach ($textNodes as $node) {
                $localName = $node->localName;
                if ($localName === 'tab') {
                    $buffer .= ' ';
                } elseif ($localName === 'br') {
                    $buffer .= "\n";
                } else {
                    $buffer .= $node->textContent;
                }
            }

            $cleaned = trim($buffer);
            if ($cleaned !== '') {
                $lines[] = $cleaned;
            }
        }

        return implode("\n", $lines);
    }
}
