<?php

declare(strict_types=1);

namespace ThreeBRS\SyliusEnterpriseSecurityPlugin\Service;

class RandomUsleepTimingPadding implements TimingPaddingInterface
{
    public function __construct(
        protected int $minMicroseconds = 50_000,
        protected int $maxMicroseconds = 150_000,
    ) {
    }

    public function pad(): void
    {
        usleep(random_int($this->minMicroseconds, $this->maxMicroseconds));
    }
}
