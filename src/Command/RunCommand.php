<?php

declare(strict_types=1);

namespace App\Command;

use App\Benchmark\Fixture;
use App\Benchmark\FixtureSuite;
use App\Benchmark\Report;
use App\Benchmark\Runner;
use App\SystemOne\ProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:system-one:run', description: 'Compare Jev and CLM using the same Symfony TypeSafe bridge and fixtures.')]
final readonly class RunCommand
{
    public function __construct(
        private FixtureSuite $suite,
        private Runner $runner,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /** @param list<string> $provider
     * @param list<string> $case
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Provider to run; repeat the option for both.', suggestedValues: ProviderRegistry::NAMES)]
        array $provider = [],
        #[Option(description: 'Fixture ID to run; repeat for several. Defaults to the whole suite.')]
        array $case = [],
        #[Option(description: 'Number of measured iterations; no automatic warm-up or retries.')]
        int $repeat = 1,
        #[Option(description: 'Display table or JSON.', suggestedValues: ['table', 'json'])]
        string $format = 'table',
        #[Option(description: 'Atomically save a JSON evidence report to this path.')]
        ?string $output = null,
        #[Option(description: 'Describe CLM deployment/checkpoint/encoder revisions used for this run.')]
        string $runtimeLabel = '',
        #[Option(description: 'List fixtures without making provider requests.')]
        bool $list = false,
    ): int {
        $fixtures = $this->suite->all();
        $known = array_map(static fn (Fixture $fixture): string => $fixture->id, $fixtures);
        if ($list) {
            $io->table(['Fixture', 'Purpose'], array_map(static fn (Fixture $fixture): array => [$fixture->id, $fixture->purpose], $fixtures));

            return 0;
        }

        $provider = array_values(array_unique([] === $provider ? ProviderRegistry::NAMES : $provider));
        if ([] !== array_diff($provider, ProviderRegistry::NAMES) || [] !== array_diff($case, $known) || $repeat < 1 || !\in_array($format, ['table', 'json'], true)) {
            $io->getErrorStyle()->error('Invalid provider, fixture, repeat count, or format. Use --help and --list for valid values.');

            return 2;
        }
        if ([] !== $case) {
            $fixtures = array_values(array_filter($fixtures, static fn (Fixture $fixture): bool => \in_array($fixture->id, $case, true)));
        }

        $lockHash = hash_file('sha256', $this->projectDir.'/composer.lock');
        if (false === $lockHash) {
            throw new \RuntimeException('composer.lock is required for a reproducible run.');
        }
        $report = new Report($this->runner->run($provider, $fixtures, $repeat), $this->suite->hash(), $lockHash, $runtimeLabel);
        $json = json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
        if (null !== $output) {
            $this->filesystem->dumpFile($output, $json);
        }
        if ('json' === $format) {
            $io->write($json, false, OutputInterface::OUTPUT_RAW);
        } else {
            $rows = [];
            foreach ($report->runs as $run) {
                $rows[] = [$run->provider, $run->fixture->id, $run->iteration, $run->status(), round($run->latencyMs, 2), implode(', ', $run->behaviorFailures) ?: $run->errorMessage ?? ''];
            }
            $io->table(['Provider', 'Fixture', 'Iteration', 'Result', 'ms', 'Detail'], $rows);
            foreach ($report->summary() as $name => $summary) {
                $io->text(\sprintf('%s: %d/%d compatible; %d behavior passes; %d errors.', $name, $summary['compatible'], $summary['attempted'], $summary['behavior_passed'], $summary['errors']));
            }
            $io->note('Small functional experiment. Probabilities, token accounting, and cache conditions are not calibrated across providers.');
            if (null !== $output) {
                $io->text('JSON report: '.$output);
            }
        }

        return $report->exitCode();
    }
}
