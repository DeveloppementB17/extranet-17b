<?php

declare(strict_types=1);

namespace App\Tests\Unit\Document;

use App\Document\ExternalDocumentUrlChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExternalDocumentUrlCheckerTest extends TestCase
{
    public function testRejectsHttpUrls(): void
    {
        $checker = new ExternalDocumentUrlChecker(new MockHttpClient());
        self::assertSame(
            'Seules les URL en https:// sont acceptées.',
            $checker->validate('http://exemple.com/doc.pdf'),
        );
    }

    public function testRejectsNotFound(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
        ]);
        $checker = new ExternalDocumentUrlChecker($client);

        self::assertSame(
            'Cette URL renvoie une erreur 404 (page introuvable).',
            $checker->validate('https://exemple.com/missing.pdf'),
        );
    }

    public function testAcceptsReachableHttps(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 200]),
        ]);
        $checker = new ExternalDocumentUrlChecker($client);

        self::assertNull($checker->validate('https://exemple.com/doc.pdf'));
    }
}
