<?php

namespace App\Services\Documents;

use App\Support\PdfWriter;
use RuntimeException;

class ImageToPdf
{
    /**
     * Convierte JPG/PNG a un PDF de una página sin alterar el contenido: solo corrige la
     * orientación EXIF, aplana transparencia sobre blanco y ajusta la imagen a la hoja.
     */
    public function convert(string $bytes): string
    {
        $image = $this->decode($bytes);
        $width = imagesx($image);
        $height = imagesy($image);

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();

        [$pageWidth, $pageHeight] = $width > $height ? [792, 612] : [612, 792];
        $margin = 18;
        $scale = min(($pageWidth - 2 * $margin) / $width, ($pageHeight - 2 * $margin) / $height);
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;
        $content = sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /Im0 Do Q',
            $drawWidth,
            $drawHeight,
            ($pageWidth - $drawWidth) / 2,
            ($pageHeight - $drawHeight) / 2,
        );

        return PdfWriter::build([
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>",
            "<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ".strlen($jpeg)." >>\nstream\n".$jpeg."\nendstream",
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
        ]);
    }

    public function decode(string $bytes): \GdImage
    {
        $info = @getimagesizefromstring($bytes);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new RuntimeException('image_unsupported');
        }

        if (config('eira.upload.max_image_pixels') < $info[0] * $info[1]
            || max($info[0], $info[1]) > config('eira.upload.max_image_side')) {
            throw new RuntimeException('image_too_large');
        }

        $previousLimit = ini_set('memory_limit', '512M');

        try {
            $image = @imagecreatefromstring($bytes);
        } finally {
            if ($previousLimit !== false) {
                ini_set('memory_limit', $previousLimit);
            }
        }

        if (! $image) {
            throw new RuntimeException('image_corrupt');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
            $angle = match ($exif['Orientation'] ?? 1) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0);
            }
        }

        return $image;
    }
}
