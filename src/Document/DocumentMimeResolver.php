<?php

declare(strict_types=1);

namespace App\Document;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

final class DocumentMimeResolver
{
    public function resolveForUpload(UploadedFile $file): string
    {
        $clientMime = strtolower((string) ($file->getClientMimeType() ?: ''));
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== '') {
            $guessed = (new MimeTypes())->getMimeTypes($extension);
            if ($guessed !== []) {
                return $guessed[0];
            }
        }

        if ($clientMime !== '' && $clientMime !== 'application/octet-stream') {
            return $clientMime;
        }

        return 'application/octet-stream';
    }
}
