<?php

namespace App\Entity;

use App\Repository\TimeCreditRepository;
use App\Tenant\EntrepriseOwnedInterface;
use App\Tenant\EntrepriseOwnedTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeCreditRepository::class)]
#[ORM\Table(name: 'time_credit')]
#[ORM\UniqueConstraint(name: 'uniq_time_credit_legacy_source_id', columns: ['legacy_source_id'])]
class TimeCredit implements EntrepriseOwnedInterface
{
    use EntrepriseOwnedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column]
    private int $totalMinutes = 0;

    #[ORM\Column]
    private int $remainingMinutes = 0;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $dossierNumber = null;

    /** URL du site web lié (17b-monitor), pour rapprocher les interventions. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $siteUrl = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $archived = false;

    /**
     * Identifiant EXTRANETB17_MAINTENANCE_CREDIT.id (réimport idempotent).
     */
    #[ORM\Column(nullable: true)]
    private ?int $legacySourceId = null;

    #[ORM\ManyToOne(targetEntity: TimeCreditCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TimeCreditCategory $category = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $createdBy = null;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, TimeCreditMovement>
     */
    #[ORM\OneToMany(mappedBy: 'timeCredit', targetEntity: TimeCreditMovement::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['occurredAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $movements;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->movements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTotalMinutes(): int
    {
        return $this->totalMinutes;
    }

    public function setTotalMinutes(int $totalMinutes): self
    {
        $this->totalMinutes = $totalMinutes;

        return $this;
    }

    public function getRemainingMinutes(): int
    {
        return $this->remainingMinutes;
    }

    public function setRemainingMinutes(int $remainingMinutes): self
    {
        $this->remainingMinutes = $remainingMinutes;

        return $this;
    }

    public function getDossierNumber(): ?string
    {
        return $this->dossierNumber;
    }

    public function setDossierNumber(?string $dossierNumber): self
    {
        $this->dossierNumber = $dossierNumber !== null && trim($dossierNumber) !== '' ? trim($dossierNumber) : null;

        return $this;
    }

    public function getSiteUrl(): ?string
    {
        return $this->siteUrl;
    }

    public function setSiteUrl(?string $siteUrl): self
    {
        if ($siteUrl === null || trim($siteUrl) === '') {
            $this->siteUrl = null;

            return $this;
        }

        $this->siteUrl = rtrim(trim($siteUrl), '/');

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): self
    {
        $this->archived = $archived;

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

    public function getCategory(): ?TimeCreditCategory
    {
        return $this->category;
    }

    public function setCategory(?TimeCreditCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, TimeCreditMovement>
     */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    public function addMovement(TimeCreditMovement $movement): self
    {
        if (!$this->movements->contains($movement)) {
            $this->movements->add($movement);
            $movement->setTimeCredit($this);
        }

        return $this;
    }
}
