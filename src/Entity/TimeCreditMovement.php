<?php

namespace App\Entity;

use App\Repository\TimeCreditMovementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeCreditMovementRepository::class)]
#[ORM\Table(name: 'time_credit_movement')]
class TimeCreditMovement
{
    public const TYPE_ALLOCATION = 'allocation';
    public const TYPE_INTERVENTION = 'intervention';
    public const TYPE_ADJUSTMENT = 'adjustment';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TimeCredit::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TimeCredit $timeCredit = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?User $createdBy = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_ADJUSTMENT;

    #[ORM\Column]
    private int $deltaMinutes = 0;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->occurredAt = $now;
        $this->createdAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTimeCredit(): ?TimeCredit
    {
        return $this->timeCredit;
    }

    public function setTimeCredit(?TimeCredit $timeCredit): self
    {
        $this->timeCredit = $timeCredit;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDeltaMinutes(): int
    {
        return $this->deltaMinutes;
    }

    public function setDeltaMinutes(int $deltaMinutes): self
    {
        $this->deltaMinutes = $deltaMinutes;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null && trim($description) !== '' ? trim($description) : null;

        return $this;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
