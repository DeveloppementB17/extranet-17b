<?php

namespace App\Tests\Functional;

use App\Entity\Entreprise;
use App\Entity\User;

final class AdminAccessTest extends DocumentWebTestCase
{
    public function test17bUserGets403OnAdminDashboard(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $staff = $em->getRepository(User::class)->findOneBy(['email' => 'staff-partial@17b.test']);
        self::assertNotNull($staff);
        $staffId = $staff->getId();
        self::assertNotNull($staffId);


        $browser->loginUser($staff);
        $browser->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function test17bAdminCanOpenAdminDashboard(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin-test@17b.test']);
        self::assertNotNull($admin);

        $browser->loginUser($admin);
        $browser->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Administration 17b', (string) $browser->getResponse()->getContent());
    }

    public function testAdminCanEditUserAndPersistManagedEntreprises(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $admin = $em->getRepository(User::class)->findOneBy(['email' => 'admin-test@17b.test']);
        $staff = $em->getRepository(User::class)->findOneBy(['email' => 'staff-partial@17b.test']);
        $agency = $em->getRepository(Entreprise::class)->findOneBy(['slug' => '17b']);
        $nord = $em->getRepository(Entreprise::class)->findOneBy(['slug' => 'demo-nord']);
        self::assertNotNull($admin);
        self::assertNotNull($staff);
        self::assertNotNull($agency);
        self::assertNotNull($nord);
        $staffId = $staff->getId();
        self::assertNotNull($staffId);

        $browser->loginUser($admin);
        $crawler = $browser->request('GET', '/admin/utilisateurs/'.$staffId.'/modifier');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form();
        $form['admin_user[email]'] = 'staff-modifie@17b.test';
        $form['admin_user[primaryRole]'] = 'ROLE_17B_USER';
        $form['admin_user[entreprise]'] = (string) $agency->getId();
        $form['admin_user[plainPassword][first]'] = '';
        $form['admin_user[plainPassword][second]'] = '';
        $browser->submit($form);
        self::assertResponseRedirects('/admin/utilisateurs');

        $em->clear();
        $updated = $em->getRepository(User::class)->find($staffId);
        self::assertNotNull($updated);
        self::assertSame('staff-modifie@17b.test', $updated->getEmail());
        self::assertSame('ROLE_17B_USER', $updated->getPrimaryStoredRole());
        self::assertContains($nord->getId(), $updated->getManagedEntrepriseIds());

        // Restaure les fixtures pour ne pas polluer les autres tests.
        $agencyFresh = $em->getRepository(Entreprise::class)->find($agency->getId());
        $nordFresh = $em->getRepository(Entreprise::class)->find($nord->getId());
        self::assertNotNull($agencyFresh);
        self::assertNotNull($nordFresh);
        $updated->setEmail('staff-partial@17b.test');
        $updated->setEntreprise($agencyFresh);
        $updated->setRoles(['ROLE_17B_USER']);
        $updated->clearManagedEntreprises();
        $updated->addManagedEntreprise($nordFresh);
        $em->flush();
    }
}
