<?php

namespace App\Support;

class PdfWriter
{
    /**
     * Ensambla un PDF 1.4 mínimo a partir de los cuerpos de sus objetos (el objeto 1 es el catálogo).
     *
     * @param  list<string>  $objects
     */
    public static function build(array $objects): string
    {
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $offsets[] = strlen($out);
            $out .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($out);
        $count = count($objects) + 1;
        $out .= "xref\n0 {$count}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $out .= sprintf("%010d 00000 n \n", $offset);
        }

        return $out."trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /**
     * PDF de una página con líneas de texto; se usa para generar documentos ficticios de demo y pruebas.
     *
     * @param  list<string>  $lines
     */
    public static function text(array $lines): string
    {
        $content = "BT /F1 13 Tf 72 720 Td 22 TL\n";

        foreach ($lines as $line) {
            $encoded = mb_convert_encoding($line, 'Windows-1252', 'UTF-8');
            $content .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded).") Tj T*\n";
        }

        $content .= 'ET';

        return self::build([
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ]);
    }
}
