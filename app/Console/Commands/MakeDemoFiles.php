<?php

namespace App\Console\Commands;

use App\Support\PdfWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeDemoFiles extends Command
{
    protected $signature = 'eira:demo-files';

    protected $description = 'Genera documentos ficticios (PDF y JPG) para la demo en storage/app/demo-samples';

    /** Folio de formato => ID EIRA (ficticios). */
    private const SAMPLES = [
        '900101' => 'PELU900101',
        '900102' => 'GOMA850505',
        '900103' => 'RISA921212',
        '900104' => 'LOTE880303',
    ];

    public function handle(): int
    {
        $dir = storage_path('app/demo-samples');
        File::ensureDirectoryExists($dir);

        foreach (array_slice(self::SAMPLES, 0, 3, true) as $folio => $eiraId) {
            File::put("{$dir}/resultado-folio-{$folio}.pdf", PdfWriter::text($this->lines($folio, $eiraId)));
        }

        File::put("{$dir}/receta-folio-900101.pdf", PdfWriter::text([
            'DOCUMENTO DEMO - DATOS FICTICIOS',
            'Receta adjunta (simulada)',
            'No. de folio: 900101 - ID EIRA: PELU900101',
            'Sin contenido clínico real.',
        ]));

        $image = imagecreatetruecolor(1200, 1600);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $ink = imagecolorallocate($image, 33, 26, 27);
        foreach ($this->lines('900104', self::SAMPLES['900104']) as $i => $line) {
            imagestring($image, 5, 80, 100 + $i * 40, mb_convert_encoding($line, 'ISO-8859-1', 'UTF-8'), $ink);
        }
        imagejpeg($image, "{$dir}/resultado-folio-900104-foto.jpg", 88);

        $this->info("Archivos DEMO generados en {$dir}");

        return self::SUCCESS;
    }

    private function lines(string $folio, string $eiraId): array
    {
        return [
            'DOCUMENTO DEMO - DATOS FICTICIOS',
            'Solicitud y reporte de citología cervical (simulado)',
            "No. de folio: {$folio}",
            "ID EIRA: {$eiraId}",
            'Laboratorio DEMO - Revisión de origen: SÍ (simulada)',
            'Este archivo no contiene información clínica real.',
        ];
    }
}
