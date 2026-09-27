<?php

declare(strict_types=1);

namespace App\Benchmark;

use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;

final readonly class Fixture
{
    /** @param array<string, string|array{min: float, max: float}> $expected */
    public function __construct(
        public string $id,
        public string $purpose,
        public Evaluation $evaluation,
        public array $expected,
    ) {
    }
}
