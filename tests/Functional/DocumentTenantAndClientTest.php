<?php

namespace App\Tests\Functional;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Entity\User;

/**
 * Vérifie les données chargées par les fixtures (entreprises, clients, documents).
 */
final class DocumentTenantAndClientTest extends DocumentWebTestCase
{
    public function testAgencyPlusThreeClientEnterprisesExist(): void
    {
        self::bootKernel();
        try {
            $em = static::getContainer()->get('doctrine')->getManager();
            $slugs = $em->getRepository(Entreprise::class)->createQueryBuilder('e')
                ->select('e.slug')
                ->orderBy('e.slug', 'ASC')
                ->getQuery()
                ->getSingleColumnResult();

            self::assertCount(4, $slugs);
            self::assertContains('17b', $slugs);
            self::assertContains('demo-nord', $slugs);
            self::assertContains('demo-sud', $slugs);
            self::assertContains('demo-est', $slugs);
        } finally {
            static::ensureKernelShutdown();
        }
    }

    public function testDemoAccountsExist(): void
    {
        self::bootKernel();
        try {
            $em = static::getContainer()->get('doctrine')->getManager();
            $emails = $em->getRepository(User::class)->createQueryBuilder('u')
                ->select('u.email')
                ->orderBy('u.email', 'ASC')
                ->getQuery()
                ->getSingleColumnResult();

            self::assertContains('admin-est@clients.test', $emails);
            self::assertContains('admin-nord@clients.test', $emails);
            self::assertContains('admin-sud@clients.test', $emails);
            self::assertContains('admin-test@17b.test', $emails);
            self::assertContains('staff-partial@17b.test', $emails);
            self::assertContains('user-est@clients.test', $emails);
            self::assertContains('user-nord@clients.test', $emails);
            self::assertContains('user-sud@clients.test', $emails);
        } finally {
            static::ensureKernelShutdown();
        }
    }

    public function testCustomerUserSeesAllDocumentsOfTheirCompany(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $clientUser = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        self::assertNotNull($clientUser);

        $browser->loginUser($clientUser);
        $browser->request('GET', '/documents');

        self::assertResponseIsSuccessful();
        $html = (string) $browser->getResponse()->getContent();
        self::assertStringContainsString('NOTE-NORD-1', $html);
        self::assertStringContainsString('NOTE-NORD-2', $html);
        self::assertStringNotContainsString('DOC-SUD', $html);
    }

    public function test17bAdminSeesDocumentsForAllClientCompanies(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $admin17b = $em->getRepository(User::class)->findOneBy(['email' => 'admin-test@17b.test']);
        self::assertNotNull($admin17b);

        $browser->loginUser($admin17b);
        $browser->request('GET', '/documents');

        self::assertResponseIsSuccessful();
        $html = (string) $browser->getResponse()->getContent();
        self::assertStringContainsString('DOC-SUD-A-001', $html);
        self::assertStringContainsString('DOC-SUD-B-001', $html);
        self::assertStringContainsString('NOTE-NORD-1', $html);
        self::assertStringContainsString('DOC-EST-001', $html);
    }

    public function test17bManagedUserSeesDocumentsWithoutSelectingClientInSwitcher(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $manager = $em->getRepository(User::class)->findOneBy(['email' => 'staff-partial@17b.test']);
        self::assertNotNull($manager);

        $browser->loginUser($manager);
        $browser->request('GET', '/documents');

        self::assertResponseIsSuccessful();
        $html = (string) $browser->getResponse()->getContent();
        self::assertStringContainsString('NOTE-NORD-1', $html);
        self::assertStringContainsString('NOTE-NORD-2', $html);
    }

    public function test17bManagedUserSeesOnlySelectedClientDocuments(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $manager = $em->getRepository(User::class)->findOneBy(['email' => 'staff-partial@17b.test']);
        $nord = $em->getRepository(Entreprise::class)->findOneBy(['slug' => 'demo-nord']);
        self::assertNotNull($manager);
        self::assertNotNull($nord);

        $browser->loginUser($manager);
        $crawler = $browser->request('GET', '/');
        $token = $crawler
            ->filter('form[action="/staff/client/'.$nord->getId().'/select"] input[name="_token"]')
            ->attr('value');
        self::assertNotFalse($token);

        $browser->request('POST', '/staff/client/'.$nord->getId().'/select', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/');

        $browser->request('GET', '/documents');
        self::assertResponseIsSuccessful();
        $html = (string) $browser->getResponse()->getContent();
        self::assertStringContainsString('NOTE-NORD-1', $html);
        self::assertStringNotContainsString('DOC-SUD-A-001', $html);
    }

    public function testCustomerCannotDownloadDocumentFromAnotherCompany(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $clientNord = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        $clientSud = $em->getRepository(User::class)->findOneBy(['email' => 'admin-sud@clients.test']);
        self::assertNotNull($clientNord);
        self::assertNotNull($clientSud);

        $sudDoc = $em->getRepository(Document::class)->findOneBy(['client' => $clientSud]);
        self::assertNotNull($sudDoc);
        $sudDocId = $sudDoc->getId();

        $browser->loginUser($clientNord);
        $browser->request('GET', '/documents/'.$sudDocId.'/download');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCustomerCannotPreviewDocumentFromAnotherCompany(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $clientNord = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        $clientSud = $em->getRepository(User::class)->findOneBy(['email' => 'admin-sud@clients.test']);
        self::assertNotNull($clientNord);
        self::assertNotNull($clientSud);

        $sudDoc = $em->getRepository(Document::class)->findOneBy(['client' => $clientSud]);
        self::assertNotNull($sudDoc);

        $browser->loginUser($clientNord);
        $browser->request('GET', '/documents/'.$sudDoc->getId().'/preview');

        self::assertResponseStatusCodeSame(403);
    }

    public function test17bManagedUserCannotPreviewOtherManagedCompanyWhenNordSelected(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $manager = $em->getRepository(User::class)->findOneBy(['email' => 'staff-partial@17b.test']);
        $nord = $em->getRepository(Entreprise::class)->findOneBy(['slug' => 'demo-nord']);
        $sud = $em->getRepository(Entreprise::class)->findOneBy(['slug' => 'demo-sud']);
        $sudDoc = $em->getRepository(Document::class)->createQueryBuilder('d')
            ->join('d.entreprise', 'e')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', 'demo-sud')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($manager);
        self::assertNotNull($nord);
        self::assertNotNull($sud);
        self::assertNotNull($sudDoc);

        // Simule un staff multi-clients (Nord + Sud) : sans fix voter, le preview Sud
        // passait même avec Nord sélectionné.
        $manager->addManagedEntreprise($sud);
        $em->flush();

        $browser->loginUser($manager);
        $crawler = $browser->request('GET', '/');
        $token = $crawler
            ->filter('form[action="/staff/client/'.$nord->getId().'/select"] input[name="_token"]')
            ->attr('value');
        self::assertNotFalse($token);

        $browser->request('POST', '/staff/client/'.$nord->getId().'/select', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/');

        $browser->request('GET', '/documents/'.$sudDoc->getId().'/preview');
        self::assertResponseStatusCodeSame(403);

        $browser->request('GET', '/documents/'.$sudDoc->getId().'/download');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCustomerCanDownloadPeerDocumentSameCompany(): void
    {
        $browser = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $clientAdmin = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        $peer = $em->getRepository(User::class)->findOneBy(['email' => 'user-nord@clients.test']);
        self::assertNotNull($clientAdmin);
        self::assertNotNull($peer);

        $peerDoc = $em->getRepository(Document::class)->findOneBy(['client' => $peer]);
        self::assertNotNull($peerDoc);
        $peerDocId = $peerDoc->getId();

        $browser->loginUser($clientAdmin);
        $browser->request('GET', '/documents/'.$peerDocId.'/download');

        self::assertResponseIsSuccessful();
    }
}
