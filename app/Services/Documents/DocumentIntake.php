<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DocumentIntake
{
    private const ALLOWED = [
        'application/pdf' => ['pdf'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
    ];

    public function __construct(private ImageToPdf $images) {}

    public function store(UploadedFile $file, string $type, User $user, string $field = 'file'): Document
    {
        return $this->persist($this->inspect($file, $field), $type, $user);
    }

    /**
     * Valida el contenido real del archivo sin guardarlo.
     *
     * @return array{mime: string, bytes: string, sha256: string}
     */
    public function inspect(UploadedFile $file, string $field = 'file'): array
    {
        $path = $file->getRealPath();
        $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! $mime || ! isset(self::ALLOWED[$mime]) || ! in_array($extension, self::ALLOWED[$mime], true)) {
            $this->fail($field, 'unsupported_type', 'El archivo no es un PDF, JPG o PNG válido.');
        }

        $bytes = file_get_contents($path);

        if ($mime === 'application/pdf') {
            if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -2048), '%%EOF')) {
                $this->fail($field, 'corrupt_pdf', 'El PDF está dañado o incompleto.');
            }
        } else {
            try {
                $this->images->decode($bytes);
            } catch (RuntimeException $e) {
                $this->fail($field, $e->getMessage(), $e->getMessage() === 'image_too_large'
                    ? 'La imagen excede las dimensiones permitidas.'
                    : 'La imagen está dañada o no se puede procesar.');
            }
        }

        $sha256 = hash('sha256', $bytes);

        if (Document::where('sha256', $sha256)->whereIn('status', Document::ACTIVE_STATUSES)->exists()) {
            $this->fail($field, 'duplicate_file', 'Este archivo ya fue recibido y sigue activo. No se creó un duplicado.');
        }

        return compact('mime', 'bytes', 'sha256');
    }

    /** @param array{mime: string, bytes: string, sha256: string} $inspected */
    public function persist(array $inspected, string $type, User $user): Document
    {
        $id = (string) Str::uuid();
        $storedPath = 'originals/'.$id.'.'.self::ALLOWED[$inspected['mime']][0];

        Storage::disk(config('eira.documents_disk'))->put($storedPath, $inspected['bytes']);

        $document = new Document([
            'type' => $type,
            'status' => Document::STATUS_PENDING_ASSOCIATION,
            'original_path' => $storedPath,
            'original_mime' => $inspected['mime'],
            'original_size' => strlen($inspected['bytes']),
            'sha256' => $inspected['sha256'],
            'uploaded_by' => $user->id,
        ]);
        $document->id = $id;
        $document->save();

        Audit::record('document.uploaded', 'ok', null, $document->id, ['type' => $type, 'mime' => $inspected['mime']]);

        return $document;
    }

    public function fail(string $field, string $code, string $message): never
    {
        Audit::record('document.upload_rejected', 'rejected', null, null, ['reason' => $code, 'field' => $field]);

        throw ValidationException::withMessages([$field => $message]);
    }
}
