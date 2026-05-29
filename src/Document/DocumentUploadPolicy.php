<?php

declare(strict_types=1);

namespace App\Document;

use App\Entity\Document;

final class DocumentUploadPolicy
{
    /**
     * Extensions autorisées à l’upload (minuscules, sans point).
     *
     * @var list<string>
     */
    private const EXTENSIONS = [
        'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'json', 'xml', 'md', 'html', 'htm',
        'zip',
    ];

    private const int TEXT_PREVIEW_MAX_BYTES = 524_288;

    /**
     * @return list<string>
     */
    public static function extensions(): array
    {
        return self::EXTENSIONS;
    }

    public static function extensionsLabel(): string
    {
        return implode(', ', array_map(static fn (string $ext): string => '.'.$ext, self::EXTENSIONS));
    }

    public static function acceptAttribute(): string
    {
        return implode(',', array_map(static fn (string $ext): string => '.'.$ext, self::EXTENSIONS));
    }

    public static function isAllowedExtension(?string $extension): bool
    {
        if ($extension === null || $extension === '') {
            return false;
        }

        return \in_array(strtolower($extension), self::EXTENSIONS, true);
    }

    public static function resolveExtension(Document $document): ?string
    {
        $fromName = pathinfo((string) ($document->getOriginalName() ?: $document->getTitle()), \PATHINFO_EXTENSION);

        return $fromName !== '' ? strtolower($fromName) : null;
    }

    public static function supportsInlinePreview(Document $document): bool
    {
        if ($document->isExternalLink()) {
            return false;
        }

        $mime = strtolower((string) ($document->getMimeType() ?: ''));
        $extension = self::resolveExtension($document);

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return true;
        }

        return self::isTextLike($mime, $extension);
    }

    public static function supportsImageThumbnail(Document $document): bool
    {
        if ($document->isExternalLink()) {
            return false;
        }

        return str_starts_with(strtolower((string) ($document->getMimeType() ?: '')), 'image/');
    }

    public static function isTextLike(?string $mime, ?string $extension): bool
    {
        $mime = strtolower((string) $mime);
        $extension = strtolower((string) $extension);

        if ($mime !== '' && (str_starts_with($mime, 'text/') || \in_array($mime, [
            'application/json',
            'application/ld+json',
            'application/xml',
            'text/xml',
            'application/csv',
        ], true))) {
            return true;
        }

        return \in_array($extension, ['txt', 'csv', 'json', 'xml', 'md', 'html', 'htm'], true);
    }

    public static function textPreviewMaxBytes(): int
    {
        return self::TEXT_PREVIEW_MAX_BYTES;
    }

    public static function previewKind(Document $document): ?string
    {
        if (!$document->isExternalLink() && str_starts_with(strtolower((string) ($document->getMimeType() ?: '')), 'image/')) {
            return 'image';
        }

        $extension = self::resolveExtension($document);
        $mime = strtolower((string) ($document->getMimeType() ?: ''));

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'pdf';
        }

        if (self::isTextLike($mime, $extension)) {
            return 'text';
        }

        return null;
    }
}
