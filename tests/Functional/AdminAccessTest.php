<?php

namespace App\Tests\Functional;

use App\Entity\User;

final class AdminAccessTest extends DocumentWebTestCase
{
    public function test17bUserGets403OnAdminDashboard(): void
    {
        $browser = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $staff = $em->getRepository(User::class)->findOneBy(['email' => 'staff-partial@17b.test']);
        self::assertNotNull($staff);

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
}
