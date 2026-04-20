<?php

declare(strict_types=1);

namespace App\AssetMapper;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * En dev, ignore manifest.json (résidu de asset-map:compile) pour que les URLs
 * pointent toujours vers les assets compilés à la volée — notamment le CSS Tailwind
 * à jour après tailwind:build, sans devoir recompiler public/assets/.
 */
final class IgnoreCompiledManifestAssetMapper implements AssetMapperInterface
{
    public function __construct(
        private readonly AssetMapperInterface $inner,
    ) {
    }

    public function getAsset(string $logicalPath): ?MappedAsset
    {
        return $this->inner->getAsset($logicalPath);
    }

    public function allAssets(): iterable
    {
        return $this->inner->allAssets();
    }

    public function getAssetFromSourcePath(string $sourcePath): ?MappedAsset
    {
        return $this->inner->getAssetFromSourcePath($sourcePath);
    }

    public function getPublicPath(string $logicalPath): ?string
    {
        return $this->inner->getAsset($logicalPath)?->publicPath;
    }
}
