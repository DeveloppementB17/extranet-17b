<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\OnboardingTourBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OnboardingTourExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly OnboardingTourBuilder $onboardingTourBuilder,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('onboarding_tour', $this->getTour(...)),
        ];
    }

    /**
     * @return array{role: string, role_label: string, auto_start: bool, steps: list<array<string, mixed>>}
     */
    public function getTour(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [
                'role' => 'guest',
                'role_label' => '',
                'auto_start' => false,
                'steps' => [],
            ];
        }

        return $this->onboardingTourBuilder->build($user);
    }
}
