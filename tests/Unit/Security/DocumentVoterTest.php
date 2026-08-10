<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use App\Security\Voter\DocumentVoter;
use App\Tenant\ManagedClientContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class DocumentVoterTest extends TestCase
{
    public function test17bUserCannotAccessOtherManagedEntrepriseWhenAnotherIsSelected(): void
    {
        $nord = $this->entreprise('Nord', 'nord', 1);
        $sud = $this->entreprise('Sud', 'sud', 2);

        $user = (new User())
            ->setEmail('staff@17b.test')
            ->setRoles(['ROLE_17B_USER'])
            ->addManagedEntreprise($nord)
            ->addManagedEntreprise($sud);

        $document = (new Document())
            ->setEntreprise($sud)
            ->setTitle('Doc Sud')
            ->setUploadedBy($user);

        $voter = new DocumentVoter($this->contextWithSelected($nord));
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $document, [DocumentVoter::DOWNLOAD]),
        );
    }

    public function test17bUserCanAccessSelectedManagedEntrepriseDocument(): void
    {
        $nord = $this->entreprise('Nord', 'nord', 1);

        $user = (new User())
            ->setEmail('staff@17b.test')
            ->setRoles(['ROLE_17B_USER'])
            ->addManagedEntreprise($nord);

        $document = (new Document())
            ->setEntreprise($nord)
            ->setTitle('Doc Nord')
            ->setUploadedBy($user);

        $voter = new DocumentVoter($this->contextWithSelected($nord));
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($token, $document, [DocumentVoter::DOWNLOAD]),
        );
    }

    public function testCustomerCannotAccessOtherEntrepriseDocument(): void
    {
        $nord = $this->entreprise('Nord', 'nord', 1);
        $sud = $this->entreprise('Sud', 'sud', 2);

        $user = (new User())
            ->setEmail('client@nord.test')
            ->setRoles(['ROLE_CUSTOMER_USER'])
            ->setEntreprise($nord);

        $document = (new Document())
            ->setEntreprise($sud)
            ->setTitle('Doc Sud')
            ->setUploadedBy($user);

        $voter = new DocumentVoter($this->contextWithSelected(null));
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $document, [DocumentVoter::DOWNLOAD]),
        );
    }

    private function contextWithSelected(?Entreprise $selected): ManagedClientContext
    {
        $session = new Session(new MockArraySessionStorage());
        if ($selected !== null) {
            $session->set('staff_selected_client_id', $selected->getId());
        }

        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $repo = $this->createMock(EntrepriseRepository::class);
        $repo->method('find')->willReturnCallback(
            static fn (mixed $id): ?Entreprise => $selected !== null && (int) $id === $selected->getId()
                ? $selected
                : null,
        );

        return new ManagedClientContext($stack, $repo);
    }

    private function entreprise(string $name, string $slug, int $id): Entreprise
    {
        $entreprise = new Entreprise(name: $name, slug: $slug);
        $ref = new \ReflectionProperty(Entreprise::class, 'id');
        $ref->setValue($entreprise, $id);

        return $entreprise;
    }
}
