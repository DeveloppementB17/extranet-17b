<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $loginCodeHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $loginCodeExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $loginCodeRequestedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetRequestedAt = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Entreprise $entreprise = null;

    /**
     * Entreprises clientes gérées par un ROLE_17B_USER (pas utilisé pour ROLE_17B_ADMIN).
     *
     * @var Collection<int, Entreprise>
     */
    #[ORM\ManyToMany(targetEntity: Entreprise::class)]
    #[ORM\JoinTable(name: 'user_managed_entreprise')]
    private Collection $managedEntreprises;

    public function __construct()
    {
        $this->managedEntreprises = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Rôles qu’un administrateur 17b peut attribuer (un seul à la fois en base).
     *
     * @return list<string>
     */
    public static function assignableRoleValues(): array
    {
        return ['ROLE_17B_ADMIN', 'ROLE_17B_USER', 'ROLE_CUSTOMER_ADMIN', 'ROLE_CUSTOMER_USER'];
    }

    /**
     * Premier rôle métier présent en base (sans ROLE_USER implicite).
     */
    public function getPrimaryStoredRole(): string
    {
        foreach (self::assignableRoleValues() as $role) {
            if (\in_array($role, $this->roles, true)) {
                return $role;
            }
        }

        return 'ROLE_CUSTOMER_USER';
    }

    public function is17bAdmin(): bool
    {
        return \in_array('ROLE_17B_ADMIN', $this->roles, true);
    }

    public function is17bUser(): bool
    {
        return \in_array('ROLE_17B_USER', $this->roles, true);
    }

    public function is17bStaff(): bool
    {
        return $this->is17bAdmin() || $this->is17bUser();
    }

    public function isCustomerAdmin(): bool
    {
        return \in_array('ROLE_CUSTOMER_ADMIN', $this->roles, true);
    }

    public function isCustomerUser(): bool
    {
        return \in_array('ROLE_CUSTOMER_USER', $this->roles, true);
    }

    /**
     * Compte rattaché à une entreprise cliente (consultation, pas équipe 17b).
     */
    public function isCustomerActor(): bool
    {
        return $this->isCustomerAdmin() || $this->isCustomerUser();
    }

    /**
     * @return list<int>
     */
    public function getManagedEntrepriseIds(): array
    {
        $ids = [];
        foreach ($this->managedEntreprises as $entreprise) {
            $id = $entreprise->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return Collection<int, Entreprise>
     */
    public function getManagedEntreprises(): Collection
    {
        return $this->managedEntreprises;
    }

    public function addManagedEntreprise(Entreprise $entreprise): self
    {
        if (!$this->managedEntreprises->contains($entreprise)) {
            $this->managedEntreprises->add($entreprise);
        }

        return $this;
    }

    public function removeManagedEntreprise(Entreprise $entreprise): self
    {
        $this->managedEntreprises->removeElement($entreprise);

        return $this;
    }

    public function managesEntreprise(Entreprise $entreprise): bool
    {
        $id = $entreprise->getId();
        if ($id === null) {
            return false;
        }

        return \in_array($id, $this->getManagedEntrepriseIds(), true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // no-op
    }

    public function getLoginCodeHash(): ?string
    {
        return $this->loginCodeHash;
    }

    public function setLoginCodeHash(?string $loginCodeHash): self
    {
        $this->loginCodeHash = $loginCodeHash;

        return $this;
    }

    public function getLoginCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->loginCodeExpiresAt;
    }

    public function setLoginCodeExpiresAt(?\DateTimeImmutable $loginCodeExpiresAt): self
    {
        $this->loginCodeExpiresAt = $loginCodeExpiresAt;

        return $this;
    }

    public function getLoginCodeRequestedAt(): ?\DateTimeImmutable
    {
        return $this->loginCodeRequestedAt;
    }

    public function setLoginCodeRequestedAt(?\DateTimeImmutable $loginCodeRequestedAt): self
    {
        $this->loginCodeRequestedAt = $loginCodeRequestedAt;

        return $this;
    }

    public function clearLoginCode(): self
    {
        $this->loginCodeHash = null;
        $this->loginCodeExpiresAt = null;
        $this->loginCodeRequestedAt = null;

        return $this;
    }

    public function getPasswordResetTokenHash(): ?string
    {
        return $this->passwordResetTokenHash;
    }

    public function setPasswordResetTokenHash(?string $passwordResetTokenHash): self
    {
        $this->passwordResetTokenHash = $passwordResetTokenHash;

        return $this;
    }

    public function getPasswordResetExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetExpiresAt;
    }

    public function setPasswordResetExpiresAt(?\DateTimeImmutable $passwordResetExpiresAt): self
    {
        $this->passwordResetExpiresAt = $passwordResetExpiresAt;

        return $this;
    }

    public function getPasswordResetRequestedAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetRequestedAt;
    }

    public function setPasswordResetRequestedAt(?\DateTimeImmutable $passwordResetRequestedAt): self
    {
        $this->passwordResetRequestedAt = $passwordResetRequestedAt;

        return $this;
    }

    public function clearPasswordReset(): self
    {
        $this->passwordResetTokenHash = null;
        $this->passwordResetExpiresAt = null;
        $this->passwordResetRequestedAt = null;

        return $this;
    }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): self
    {
        $this->entreprise = $entreprise;

        return $this;
    }

    public function clearManagedEntreprises(): self
    {
        $this->managedEntreprises->clear();

        return $this;
    }
}

