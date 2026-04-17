<?php

namespace App\DataFixtures;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Storage\StoragePath;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu minimal : agence 17b + 3 entreprises clientes, comptes de test dédiés.
 *
 * Compte admin de test : admin-test@17b.test (ROLE_17B_ADMIN), mot de passe ChangeMe123!
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly StoragePath $storagePath,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $password = 'ChangeMe123!';

        $agency = new Entreprise(name: '17b', slug: '17b', agency: true);
        $manager->persist($agency);

        $nord = new Entreprise(name: 'Cliente Nord', slug: 'demo-nord');
        $manager->persist($nord);

        $sud = new Entreprise(name: 'Cliente Sud', slug: 'demo-sud');
        $manager->persist($sud);

        $est = new Entreprise(name: 'Cliente Est', slug: 'demo-est');
        $manager->persist($est);

        $adminTest = (new User())
            ->setEmail('admin-test@17b.test')
            ->setEntreprise($agency)
            ->setRoles(['ROLE_17B_ADMIN']);
        $adminTest->setPassword($this->passwordHasher->hashPassword($adminTest, $password));
        $manager->persist($adminTest);

        $staffPartial = (new User())
            ->setEmail('staff-partial@17b.test')
            ->setEntreprise($agency)
            ->setRoles(['ROLE_17B_USER']);
        $staffPartial->addManagedEntreprise($nord);
        $staffPartial->setPassword($this->passwordHasher->hashPassword($staffPartial, $password));
        $manager->persist($staffPartial);

        $adminNord = (new User())
            ->setEmail('admin-nord@clients.test')
            ->setEntreprise($nord)
            ->setRoles(['ROLE_CUSTOMER_ADMIN']);
        $adminNord->setPassword($this->passwordHasher->hashPassword($adminNord, $password));
        $manager->persist($adminNord);

        $userNord = (new User())
            ->setEmail('user-nord@clients.test')
            ->setEntreprise($nord)
            ->setRoles(['ROLE_CUSTOMER_USER']);
        $userNord->setPassword($this->passwordHasher->hashPassword($userNord, $password));
        $manager->persist($userNord);

        $adminSud = (new User())
            ->setEmail('admin-sud@clients.test')
            ->setEntreprise($sud)
            ->setRoles(['ROLE_CUSTOMER_ADMIN']);
        $adminSud->setPassword($this->passwordHasher->hashPassword($adminSud, $password));
        $manager->persist($adminSud);

        $userSud = (new User())
            ->setEmail('user-sud@clients.test')
            ->setEntreprise($sud)
            ->setRoles(['ROLE_CUSTOMER_USER']);
        $userSud->setPassword($this->passwordHasher->hashPassword($userSud, $password));
        $manager->persist($userSud);

        $adminEst = (new User())
            ->setEmail('admin-est@clients.test')
            ->setEntreprise($est)
            ->setRoles(['ROLE_CUSTOMER_ADMIN']);
        $adminEst->setPassword($this->passwordHasher->hashPassword($adminEst, $password));
        $manager->persist($adminEst);

        $userEst = (new User())
            ->setEmail('user-est@clients.test')
            ->setEntreprise($est)
            ->setRoles(['ROLE_CUSTOMER_USER']);
        $userEst->setPassword($this->passwordHasher->hashPassword($userEst, $password));
        $manager->persist($userEst);

        $identite = (new DocumentCategory())->setEntreprise($nord)->setName('Identité visuelle');
        $manager->persist($identite);
        $logos = (new DocumentCategory())->setEntreprise($nord)->setName('Logos')->setParent($identite);
        $manager->persist($logos);
        $chartes = (new DocumentCategory())->setEntreprise($nord)->setName('Chartes')->setParent($identite);
        $manager->persist($chartes);
        $elements = (new DocumentCategory())->setEntreprise($nord)->setName('Éléments graphiques')->setParent($identite);
        $manager->persist($elements);
        $strategie = (new DocumentCategory())->setEntreprise($nord)->setName('Stratégie de communication');
        $manager->persist($strategie);
        $strategieChild = (new DocumentCategory())->setEntreprise($nord)->setName('Stratégie')->setParent($strategie);
        $manager->persist($strategieChild);
        $pilotage = (new DocumentCategory())->setEntreprise($nord)->setName('Pilotage')->setParent($strategie);
        $manager->persist($pilotage);

        $sudCat = (new DocumentCategory())->setEntreprise($sud)->setName('Livrables');
        $manager->persist($sudCat);

        $estCat = (new DocumentCategory())->setEntreprise($est)->setName('Livrables');
        $manager->persist($estCat);

        $manager->flush();

        $this->persistDocumentFromSampleFile($manager, $nord, $adminNord, $adminTest, $logos, 'NOTE-NORD-1', 'note-nord-1.txt', 'demo-note-nord-1.txt');
        $this->persistDocumentFromSampleFile($manager, $nord, $userNord, $adminTest, $logos, 'NOTE-NORD-2', 'note-nord-2.txt', 'demo-note-nord-2.txt');
        $this->persistDocumentFromSampleFile($manager, $sud, $adminSud, $adminTest, $sudCat, 'DOC-SUD-A-001', 'livrable-sud-a.txt', 'demo-sud-a.txt');
        $this->persistDocumentFromSampleFile($manager, $sud, $userSud, $adminTest, $sudCat, 'DOC-SUD-B-001', 'livrable-sud-b.txt', 'demo-sud-b.txt');
        $this->persistDocumentFromSampleFile($manager, $est, $adminEst, $adminTest, $estCat, 'DOC-EST-001', 'livrable-est.txt', 'demo-est-1.txt');

        $manager->flush();
    }

    private function persistDocumentFromSampleFile(
        ObjectManager $manager,
        Entreprise $entreprise,
        User $client,
        User $uploadedBy,
        ?DocumentCategory $category,
        string $title,
        string $originalName,
        string $storageFileName,
    ): void {
        $companyKey = $entreprise->getSlug() ?: (string) $entreprise->getId();
        $relativeDir = sprintf('companies/%s/documents', $companyKey);
        $relativePath = $relativeDir.'/'.$storageFileName;

        $absoluteDir = rtrim($this->storagePath->root, '/').'/'.$relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException(sprintf('Impossible de créer le répertoire : %s', $absoluteDir));
        }

        $source = dirname(__DIR__, 2).'/tests/fixtures/sample-document.txt';
        if (!is_readable($source)) {
            throw new \RuntimeException('Fichier source fixtures introuvable : '.$source);
        }

        $dest = $absoluteDir.'/'.$storageFileName;
        if (!copy($source, $dest)) {
            throw new \RuntimeException('Copie fixture vers storage impossible : '.$dest);
        }

        $size = filesize($dest);
        if ($size === false) {
            throw new \RuntimeException('Impossible de lire la taille du fichier : '.$dest);
        }

        $doc = new Document();
        $doc->setEntreprise($entreprise);
        $doc->setClient($client);
        $doc->setUploadedBy($uploadedBy);
        $doc->setTitle($title);
        $doc->setOriginalName($originalName);
        $doc->setStorageName($storageFileName);
        $doc->setStoragePath($relativePath);
        $doc->setMimeType('text/plain');
        $doc->setSize((int) $size);
        if ($category !== null) {
            $doc->setCategory($category);
        }

        $manager->persist($doc);
    }
}
