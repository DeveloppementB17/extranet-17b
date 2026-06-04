<?php

namespace App\Service;

use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use App\Repository\TimeCreditMovementRepository;

/**
 * Recalcule total et solde restant à partir de l’historique des mouvements.
 */
final class TimeCreditBalanceRecalculator
{
    public function __construct(
        private readonly TimeCreditMovementRepository $movementRepository,
    ) {
    }

    public function recalculate(TimeCredit $credit): void
    {
        $total = 0;
        $remaining = 0;

        foreach ($this->movementRepository->findByCreditChronological($credit) as $movement) {
            $delta = $movement->getDeltaMinutes();
            if ($movement->getType() === TimeCreditMovement::TYPE_INTERVENTION) {
                $remaining += $delta;

                continue;
            }

            $total += $delta;
            $remaining += $delta;
        }

        $credit->setTotalMinutes(max(0, $total));
        $credit->setRemainingMinutes(max(0, $remaining));
        $credit->setArchived($total > 0 && $remaining <= 0);
    }
}
