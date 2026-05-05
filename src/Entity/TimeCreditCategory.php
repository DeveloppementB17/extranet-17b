<?php

namespace App\Entity;

use App\Repository\TimeCreditCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeCreditCategoryRepository::class)]
#[ORM\Table(name: 'time_credit_category')]
class TimeCreditCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $name = '';

    /**
     * @var Collection<int, TimeCredit>
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: TimeCredit::class)]
    private Collection $timeCredits;

    public function __construct()
    {
        $this->timeCredits = new ArrayCollection();
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

    /**
     * @return Collection<int, TimeCredit>
     */
    public function getTimeCredits(): Collection
    {
        return $this->timeCredits;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
