<?php

namespace App\Service;

/**
 * Formatage des durées crédits temps (minutes ↔ Hhii).
 */
final class DurationFormatter
{
    /**
     * Affichage principal : ≥ 60 min → 27h30, sinon 45mn.
     */
    public function primary(int $minutes): string
    {
        if (abs($minutes) >= 60) {
            return $this->hoursMinutes($minutes);
        }

        return sprintf('%dmn', $minutes);
    }

    /**
     * Libellé standard d’un crédit temps (ex. « Crédit temps 32h »).
     */
    public function creditLabel(int $totalMinutes): string
    {
        return 'Crédit temps '.$this->primary(max(0, $totalMinutes));
    }

    /**
     * Affichage alternatif (tooltip) : inverse du format principal.
     */
    public function alternate(int $minutes): string
    {
        if (abs($minutes) >= 60) {
            return sprintf('%dmn', $minutes);
        }

        return $this->hoursMinutes($minutes);
    }

    /**
     * Convertit des minutes en format Hhii (ex. 27h30, 32h, -1h15).
     */
    public function hoursMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $absolute = abs($minutes);
        $hours = intdiv($absolute, 60);
        $remainingMinutes = $absolute % 60;

        if ($remainingMinutes === 0) {
            return sprintf('%s%dh', $sign, $hours);
        }

        return sprintf('%s%dh%02d', $sign, $hours, $remainingMinutes);
    }
}