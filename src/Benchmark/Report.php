<?php

declare(strict_types=1);

namespace App\Benchmark;

use Composer\InstalledVersions;

final readonly class Report implements \JsonSerializable
{
    /** @param list<RunResult> $runs */
    public function __construct(
        public array $runs,
        public string $suiteHash,
        public string $lockHash,
        public string $runtimeLabel,
    ) {
    }

    public function exitCode(): int
    {
        if ([] === $this->runs) {
            return 2;
        }
        foreach ($this->runs as $run) {
            if (null !== $run->errorKind) {
                return 2;
            }
        }
        foreach ($this->runs as $run) {
            if ([] !== $run->behaviorFailures) {
                return 1;
            }
        }

        return 0;
    }

    /** @return array<string, array{attempted: int, compatible: int, behavior_passed: int, errors: int, median_success_ms: ?float}> */
    public function summary(): array
    {
        $summary = [];
        $latencies = [];
        foreach ($this->runs as $run) {
            $summary[$run->provider] ??= ['attempted' => 0, 'compatible' => 0, 'behavior_passed' => 0, 'errors' => 0, 'median_success_ms' => null];
            ++$summary[$run->provider]['attempted'];
            if (null === $run->outcome) {
                ++$summary[$run->provider]['errors'];
                continue;
            }
            ++$summary[$run->provider]['compatible'];
            if ([] === $run->behaviorFailures) {
                ++$summary[$run->provider]['behavior_passed'];
            }
            $latencies[$run->provider][] = $run->latencyMs;
        }
        foreach ($summary as $provider => $item) {
            $times = $latencies[$provider] ?? [];
            if ([] === $times) {
                continue;
            }
            sort($times, \SORT_NUMERIC);
            $middle = intdiv(\count($times), 2);
            $median = 0 === \count($times) % 2 ? ($times[$middle - 1] + $times[$middle]) / 2 : $times[$middle];
            $summary[$provider]['median_success_ms'] = round($median, 3);
        }

        return $summary;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $dependencies = [];
        foreach (['symfony/framework-bundle', 'symfony/ai-bundle', 'symfony/ai-platform', 'symfony/ai-type-safe-platform'] as $package) {
            $dependencies[$package] = ['version' => InstalledVersions::getPrettyVersion($package), 'reference' => InstalledVersions::getReference($package)];
        }

        return [
            'schema_version' => 1,
            'mode' => 'live',
            'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DATE_ATOM),
            'suite_sha256' => $this->suiteHash,
            'composer_lock_sha256' => $this->lockHash,
            'runtime_label' => $this->runtimeLabel,
            'php' => \PHP_VERSION,
            'dependencies' => $dependencies,
            'cache_state' => 'uncontrolled; iterations are reported, no cold-cache assumption',
            'score_semantics' => 'Choice distributions are relative to their candidate set; cross-provider calibration is unmeasured.',
            'usage_semantics' => 'Provider-reported input units are not comparable: CLM counts encoder cache misses.',
            'summary' => $this->summary(),
            'runs' => $this->runs,
            'exit_code' => $this->exitCode(),
        ];
    }
}
