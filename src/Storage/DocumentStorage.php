<?php

namespace App\Storage;

use App\Entity\Document;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class DocumentStorage
{
    private AsciiSlugger $slugger;

    public function __construct(
        private readonly StoragePath $storagePath,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    /**
     * Déplace le fichier uploadé vers le storage privé et hydrate l'entité Document.
     *
     * @return array{absolutePath: string, relativePath: string, storageName: string}
     */
    public function storeUploadedFile(UploadedFile $file, User $user): array
    {
        $entreprise = $user->getEntreprise();
        $companyKey = $entreprise?->getSlug() ?: (string) $entreprise?->getId();

        $originalName = $file->getClientOriginalName();
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $guessed = $file->guessExtension();
            $ext = is_string($guessed) ? strtolower($guessed) : '';
        }
        $safeBase = (string) $this->slugger->slug($base);
        $safeBase = $safeBase !== '' ? $safeBase : 'document';

        $suffix = bin2hex(random_bytes(6));
        $storageName = $ext !== ''
            ? sprintf('%s-%s.%s', $safeBase, $suffix, $ext)
            : sprintf('%s-%s', $safeBase, $suffix);
        $relativeDir = sprintf('companies/%s/documents', $companyKey ?: 'unknown');
        $relativePath = $relativeDir.'/'.$storageName;

        $absoluteDir = rtrim($this->storagePath->root, '/').'/'.$relativeDir;
        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0775, true);
        }

        $file->move($absoluteDir, $storageName);

        return [
            'absolutePath' => $absoluteDir.'/'.$storageName,
            'relativePath' => $relativePath,
            'storageName' => $storageName,
        ];
    }

    public function resolveAbsolutePath(Document $document): string
    {
        $relative = $document->getStoragePath();
        if ($relative === null || $relative === '') {
            throw new \InvalidArgumentException('Document sans fichier local (lien externe ou données incomplètes).');
        }

        return rtrim($this->storagePath->root, '/').'/'.ltrim($relative, '/');
    }
}

