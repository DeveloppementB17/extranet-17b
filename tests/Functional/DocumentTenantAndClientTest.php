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

    public function test17bManagedUserDoesNotSeeUnmanagedCompanyDocuments(): void
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
