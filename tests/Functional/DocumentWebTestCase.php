<?php

namespace App\Tests\Functional;

use App\Tests\Support\TestFixturesLoader;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class DocumentWebTestCase extends WebTestCase
{
    private static bool $fixturesLoaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$fixturesLoaded) {
            return;
        }

        static::bootKernel();
        try {
            TestFixturesLoader::load(static::getContainer());
        } finally {
            static::ensureKernelShutdown();
        }

        self::$fixturesLoaded = true;
    }
}
