<?php

declare(strict_types=1);

final class BenchmarkFeatureService
{
    public function __construct(private readonly BenchmarkFeatureValue $dependency)
    {
    }

    public function value(): string
    {
        return $this->dependency->value() . ':enabled';
    }
}
