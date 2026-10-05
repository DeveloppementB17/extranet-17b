<?php

namespace App\Twig;

use App\Service\DurationFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class DurationExtension extends AbstractExtension
{
    public function __construct(
        private readonly DurationFormatter $durationFormatter,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('duration_primary', $this->durationFormatter->primary(...)),
            new TwigFilter('duration_alternate', $this->durationFormatter->alternate(...)),
            new TwigFilter('duration_hours_minutes', $this->durationFormatter->hoursMinutes(...)),
        ];
    }
}
