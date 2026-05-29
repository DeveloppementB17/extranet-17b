<?php

declare(strict_types=1);

namespace App\Entreprise;

use App\Repository\EntrepriseRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class EntrepriseSlugGenerator
{
    private const int MAX_LENGTH = 64;

    public function __construct(
        private readonly EntrepriseRepository $entrepriseRepository,
    ) {
    }

    public function generateUniqueSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        $base = mb_strtolower((string) $slugger->slug($name));
        $base = trim($base, '-');
        $base = (string) preg_replace('/-+/', '-', $base);

        if ($base === '') {
            $base = 'entreprise';
        }

        if (strlen($base) > self::MAX_LENGTH) {
            $base = rtrim(substr($base, 0, self::MAX_LENGTH), '-');
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->entrepriseRepository->findOneBySlug($candidate) !== null) {
            $suffixPart = '-'.$suffix;
            $candidate = rtrim(substr($base, 0, self::MAX_LENGTH - strlen($suffixPart)), '-').$suffixPart;
            ++$suffix;
        }

        return $candidate;
    }
}
