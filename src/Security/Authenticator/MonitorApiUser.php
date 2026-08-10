<?php

namespace App\Security\Authenticator;

use Symfony\Component\Security\Core\User\UserInterface;

final class MonitorApiUser implements UserInterface
{
    public function getUserIdentifier(): string
    {
        return 'monitor-api';
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_MONITOR_API'];
    }

    public function eraseCredentials(): void
    {
    }
}
