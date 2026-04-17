<?php

namespace App\Tests\Support;

use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Purge la base puis exécute toutes les fixtures enregistrées (services tagués),
 * comme `doctrine:fixtures:load` mais sans sous-processus.
 */
final class TestFixturesLoader
{
    public static function load(ContainerInterface $container): void
    {
        $registry = $container->get('doctrine');
        $em = $registry->getManager();

        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);

        /** @var SymfonyFixturesLoader $loader */
        $loader = $container->get('doctrine.fixtures.loader');

        $executor->execute($loader->getFixtures());
    }
}
