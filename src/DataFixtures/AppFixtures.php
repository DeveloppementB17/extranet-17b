<?php

namespace App\DataFixtures;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $entreprise = new Entreprise(name: '17b', slug: '17b');
        $manager->persist($entreprise);

        $password = 'ChangeMe123!';

        $admin = (new User())
            ->setEmail('admin@17b.test')
            ->setEntreprise($entreprise)
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));
        $manager->persist($admin);

        $managerUser = (new User())
            ->setEmail('manager@17b.test')
            ->setEntreprise($entreprise)
            ->setRoles(['ROLE_MANAGER']);
        $managerUser->setPassword($this->passwordHasher->hashPassword($managerUser, $password));
        $manager->persist($managerUser);

        $client = (new User())
            ->setEmail('client@17b.test')
            ->setEntreprise($entreprise)
            ->setRoles(['ROLE_CLIENT']);
        $client->setPassword($this->passwordHasher->hashPassword($client, $password));
        $manager->persist($client);

        $user = (new User())
            ->setEmail('user@17b.test')
            ->setEntreprise($entreprise)
            ->setRoles([]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $manager->persist($user);

        $manager->flush();
    }
}
