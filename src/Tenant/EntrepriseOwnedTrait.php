<?php

namespace App\Tenant;

use App\Entity\Entreprise;
use Doctrine\ORM\Mapping as ORM;

trait EntrepriseOwnedTrait
{
    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Entreprise $entreprise = null;

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): self
    {
        $this->entreprise = $entreprise;

        return $this;
    }
}

