<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\SystemOne\EvaluationOutcome;
use App\SystemOne\Evaluator;
use App\SystemOne\ProviderNotConfigured;
use App\SystemOne\ProviderRegistry;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ChoiceAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\NoulAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ScoreAnswer;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final readonly class Runner
{
    public function __construct(private Evaluator $evaluator)
    {
    }

    /** @param list<string> $providers
     * @param list<Fixture> $fixtures
     *
     * @return list<RunResult>
     */
    public function run(array $providers, array $fixtures, int $repeat): array
    {
        if ([] === $providers || [] === $fixtures || $repeat < 1 || [] !== array_diff($providers, ProviderRegistry::NAMES)) {
            throw new \InvalidArgumentException('Select at least one supported provider and fixture, and a positive repeat count.');
        }
        $runs = [];
        for ($iteration = 1; $iteration <= $repeat; ++$iteration) {
            // Alternate provider order to reduce a fixed order effect; this is still a small sequential experiment.
            $order = 0 === $iteration % 2 ? array_reverse($providers) : $providers;
            foreach ($fixtures as $fixture) {
                foreach ($order as $provider) {
                    $start = hrtime(true);
                    try {
                        $outcome = $this->evaluator->evaluate($provider, $fixture->evaluation);
                        $runs[] = new RunResult($provider, $fixture, $iteration, (hrtime(true) - $start) / 1e6, $outcome, $this->judge($fixture, $outcome));
                    } catch (ProviderNotConfigured|PlatformException|TransportExceptionInterface|DecodingExceptionInterface|\UnexpectedValueException $exception) {
                        [$kind, $message] = $this->failure($exception);
                        $runs[] = new RunResult($provider, $fixture, $iteration, (hrtime(true) - $start) / 1e6, errorKind: $kind, errorMessage: $message);
                    }
                }
            }
        }

        return $runs;
    }

    /** @return list<string> */
    private function judge(Fixture $fixture, EvaluationOutcome $outcome): array
    {
        $failures = [];
        foreach ($fixture->expected as $id => $expected) {
            $answer = $outcome->answers->get($id);
            $actual = match (true) {
                $answer instanceof NoulAnswer => $answer->getProbability(),
                $answer instanceof ChoiceAnswer => $answer->getChoice(),
                $answer instanceof ScoreAnswer => $answer->getScore(),
                default => throw new \LogicException('Unsupported answer in the fixture judge.'),
            };
            if (\is_string($expected) ? $actual !== $expected : (!\is_float($actual) || $actual < $expected['min'] || $actual > $expected['max'])) {
                $failures[] = $id;
            }
        }

        return $failures;
    }

    /** @return array{string, string} */
    private function failure(\Throwable $exception): array
    {
        // Provider exceptions can embed URLs, credentials, response bodies or request state.
        // Only our own fixed diagnostic strings enter shareable reports.
        return match (true) {
            $exception instanceof ProviderNotConfigured => ['configuration', $exception->getMessage()],
            $exception instanceof TransportExceptionInterface => ['transport', 'Connection or timeout failure; check the selected endpoint and server availability.'],
            $exception instanceof DecodingExceptionInterface => ['contract', 'Provider returned an undecodable response.'],
            $exception instanceof \UnexpectedValueException => ['contract', $exception->getMessage()],
            $exception instanceof AuthenticationException => ['provider', 'Provider rejected authentication; check its API key.'],
            $exception instanceof RateLimitExceededException => ['provider', 'Provider rate limit reached; rerun after the limit clears.'],
            default => ['provider', 'Symfony AI rejected the request or response ('.$exception::class.'). Check model configuration and endpoint compatibility.'],
        };
    }
}
