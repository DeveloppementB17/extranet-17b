<?php

namespace App\Tests\Functional;

use App\Entity\User;

final class CustomerUserManagementTest extends DocumentWebTestCase
{
    public function testCustomerAdminSeesOnlyCustomerUsersFromOwnEntreprise(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $adminNord = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        self::assertNotNull($adminNord);

        $browser->loginUser($adminNord);
        $browser->request('GET', '/compte/utilisateurs');

        self::assertResponseIsSuccessful();
        $html = (string) $browser->getResponse()->getContent();
        self::assertStringContainsString('user-nord@clients.test', $html);
        self::assertStringNotContainsString('admin-nord@clients.test', $html);
        self::assertStringNotContainsString('user-sud@clients.test', $html);
    }

    public function testCustomerAdminCanCreateCustomerUserInOwnEntreprise(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $adminNord = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        self::assertNotNull($adminNord);

        $browser->loginUser($adminNord);
        $crawler = $browser->request('GET', '/compte/utilisateurs/nouveau');
        self::assertResponseIsSuccessful();

        $email = sprintf('nouveau-%s@clients.test', substr((string) microtime(true), -6));
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['customer_user[email]'] = $email;
        $form['customer_user[plainPassword][first]'] = 'ChangeMe123!';
        $form['customer_user[plainPassword][second]'] = 'ChangeMe123!';
        $browser->submit($form);

        self::assertResponseRedirects('/compte/utilisateurs');

        $em->clear();
        $created = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($created);
        self::assertSame('ROLE_CUSTOMER_USER', $created->getPrimaryStoredRole());
        self::assertSame($adminNord->getEntreprise()?->getId(), $created->getEntreprise()?->getId());

        // Nettoyage du test.
        $em->remove($created);
        $em->flush();
    }

    public function testCustomerAdminCannotEditCustomerUserFromAnotherEntreprise(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $adminNord = $em->getRepository(User::class)->findOneBy(['email' => 'admin-nord@clients.test']);
        $userSud = $em->getRepository(User::class)->findOneBy(['email' => 'user-sud@clients.test']);
        self::assertNotNull($adminNord);
        self::assertNotNull($userSud);
        $userSudId = $userSud->getId();
        self::assertNotNull($userSudId);

        $browser->loginUser($adminNord);
        $browser->request('GET', '/compte/utilisateurs/'.$userSudId.'/modifier');

        self::assertResponseStatusCodeSame(403);
    }
}
