<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletTransaction;

class ReceiptPdfService
{
    /** @param array<int, string> $lines */
    public function makeReport(string $title, array $lines): string
    {
        $pages = collect($lines)
            ->chunk(40)
            ->map(fn ($page) => array_merge([$title, str_repeat('-', 45)], $page->all()))
            ->all();

        return $this->buildPages($pages ?: [[$title, 'Tidak ada data.']]);
    }

    public function make(OutletTransaction $order, Outlet $outlet): string
    {
        $lines = [
            'SALES GO - RECEIPT',
            'Nomor: '.$order->document_number,
            'Tanggal: '.optional($order->committed_at)->format('d-m-Y H:i'),
            'Outlet: '.$outlet->name,
            str_repeat('-', 45),
        ];
        foreach ($order->items ?? [] as $item) {
            $lines[] = ($item['productName'] ?? 'Produk').' x'.($item['quantity'] ?? 0);
            $lines[] = '  Rp '.number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.');
        }
        $lines[] = str_repeat('-', 45);
        $lines[] = 'TOTAL: Rp '.number_format((float) $order->amount, 0, ',', '.');
        $lines[] = 'Terima kasih.';

        return $this->build($lines);
    }

    /** @param array<int, string> $lines */
    private function build(array $lines): string
    {
        return $this->buildPages(array_chunk($lines, 46));
    }

    /** @param array<int, array<int, string>> $pages */
    private function buildPages(array $pages): string
    {
        $pageObjectNumbers = collect(array_keys($pages))->map(fn (int $index) => 4 + ($index * 2));
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids ['.$pageObjectNumbers->map(fn (int $number) => $number.' 0 R')->implode(' ').'] /Count '.count($pages).' >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        foreach ($pages as $index => $lines) {
            $pageNumber = 4 + ($index * 2);
            $contentNumber = $pageNumber + 1;
            $content = $this->content($lines);
            $objects[$pageNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentNumber.' 0 R >>';
            $objects[$contentNumber] = '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream";
        }
        ksort($objects);
        $pdf = '%PDF-1.4'."\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

    /** @param array<int, string> $lines */
    private function content(array $lines): string
    {
        $content = "BT\n/F1 11 Tf\n50 790 Td\n";
        foreach ($lines as $line) {
            $text = iconv('UTF-8', 'Windows-1252//TRANSLIT', $line) ?: $line;
            $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
            $content .= '('.$text.") Tj\n0 -16 Td\n";
        }

        return $content.'ET';
    }
}
