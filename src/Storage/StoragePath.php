<?php

namespace App\Storage;

final class StoragePath
{
    public function __construct(
        public readonly string $root,
    ) {
    }
}

