<?php

namespace App\Entity;

use App\Repository\EntrepriseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ORM\Table(name: 'entreprise')]
#[ORM\UniqueConstraint(name: 'uniq_entreprise_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_entreprise_legacy_source_id', columns: ['legacy_source_id'])]
class Entreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column(length: 64)]
    private string $slug;

    /**
     * Si vrai : agence 17b (pas une entreprise cliente). Les comptes clients ont toujours agency = false.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $agency = false;

    /**
     * Si vrai : client issu de l’ancien extranet (masqué par défaut, visible via « Afficher anciennes données »).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $legacy = false;

    /**
     * Identifiant EXTRANETB17_ENTREPRISE.id_ent (réimport idempotent).
     */
    #[ORM\Column(nullable: true)]
    private ?int $legacySourceId = null;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name = '', string $slug = '', bool $agency = false)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->agency = $agency;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function isAgency(): bool
    {
        return $this->agency;
    }

    public function setAgency(bool $agency): self
    {
        $this->agency = $agency;

        return $this;
    }

    public function isLegacy(): bool
    {
        return $this->legacy;
    }

    public function setLegacy(bool $legacy): self
    {
        $this->legacy = $legacy;

        return $this;
    }

    public function getLegacySourceId(): ?int
    {
        return $this->legacySourceId;
    }

    public function setLegacySourceId(?int $legacySourceId): self
    {
        $this->legacySourceId = $legacySourceId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function __toString(): string
    {
        return $this->name !== '' ? $this->name : $this->slug;
    }
}

