<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entreprise;

use App\Entreprise\EntrepriseSlugGenerator;
use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use PHPUnit\Framework\TestCase;

final class EntrepriseSlugGeneratorTest extends TestCase
{
    public function testGenerateSlugFromName(): void
    {
        $repository = $this->createMock(EntrepriseRepository::class);
        $repository->method('findOneBySlug')->willReturn(null);

        $generator = new EntrepriseSlugGenerator($repository);

        self::assertSame('cliente-nord', $generator->generateUniqueSlug('Cliente Nord'));
    }

    public function testGenerateUniqueSlugWhenBaseExists(): void
    {
        $repository = $this->createMock(EntrepriseRepository::class);
        $repository->method('findOneBySlug')
            ->willReturnCallback(static function (string $slug): ?Entreprise {
                return $slug === 'acme' ? new Entreprise() : null;
            });

        $generator = new EntrepriseSlugGenerator($repository);

        self::assertSame('acme-2', $generator->generateUniqueSlug('Acme'));
    }
}
