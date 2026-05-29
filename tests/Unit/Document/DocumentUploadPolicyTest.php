<?php

declare(strict_types=1);

namespace App\Tests\Unit\Document;

use App\Document\DocumentUploadPolicy;
use App\Entity\Document;
use PHPUnit\Framework\TestCase;

final class DocumentUploadPolicyTest extends TestCase
{
    public function testJsonIsTextPreviewable(): void
    {
        $document = (new Document())
            ->setTitle('config.json')
            ->setOriginalName('config.json')
            ->setMimeType('application/json');

        self::assertSame('text', DocumentUploadPolicy::previewKind($document));
        self::assertTrue(DocumentUploadPolicy::supportsInlinePreview($document));
    }

    public function testExtensionsLabelListsAllFormats(): void
    {
        self::assertStringContainsString('.json', DocumentUploadPolicy::extensionsLabel());
        self::assertStringContainsString('.pdf', DocumentUploadPolicy::extensionsLabel());
    }
}
