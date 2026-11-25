<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Clock\ClockInterface;

final readonly class FrozenClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $now = new \DateTimeImmutable('2026-11-24 12:00:00')) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
