<?php

declare(strict_types=1);

namespace App\Tests\Benchmark;

use App\Benchmark\Fixture;
use App\Benchmark\Report;
use App\Benchmark\Runner;
use App\Tests\Support\WireSamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RunnerTest extends TestCase
{
    private static function fixture(string $winner = 'worker'): Fixture
    {
        return new Fixture('synthetic', 'Synthetic transport test', WireSamples::evaluation(), ['route' => $winner]);
    }

    public function testWrongModelAnswerRemainsACompatibleButFailedBehavior(): void
    {
        $runner = new Runner(WireSamples::evaluator(new MockHttpClient(new MockResponse(json_encode(WireSamples::response(), \JSON_THROW_ON_ERROR)))));
        $runs = $runner->run(['jev'], [self::fixture('check')], 1);
        $report = new Report($runs, 'suite', 'lock', 'synthetic test');
        self::assertSame(1, $report->exitCode());
        self::assertSame('passed', $runs[0]->jsonSerialize()['contract']);
        self::assertSame(['route'], $runs[0]->behaviorFailures);
        self::assertSame(1, $report->summary()['jev']['compatible']);
        self::assertSame(0, $report->summary()['jev']['behavior_passed']);
    }

    #[DataProvider('failures')]
    public function testFailuresRemainVisibleWithoutLeakingProviderBodies(int $status, string $body, string $kind): void
    {
        $client = new MockHttpClient(new MockResponse($body, ['http_code' => $status]));
        $runs = (new Runner(WireSamples::evaluator($client)))->run(['jev'], [self::fixture()], 1);
        $report = new Report($runs, 'suite', 'lock', 'synthetic test');
        self::assertSame(2, $report->exitCode());
        self::assertSame($kind, $runs[0]->errorKind);
        self::assertStringNotContainsString('TOP_SECRET', json_encode($report, \JSON_THROW_ON_ERROR));
        self::assertNull($report->summary()['jev']['median_success_ms']);
    }

    public static function failures(): iterable
    {
        yield 'authentication' => [401, '{"detail":"TOP_SECRET"}', 'provider'];
        yield 'rate limit' => [429, '{"detail":"TOP_SECRET"}', 'provider'];
        yield 'bad request' => [422, '{"detail":{"message":"TOP_SECRET"}}', 'provider'];
        yield 'server' => [503, '{"detail":"TOP_SECRET"}', 'provider'];
        yield 'non-JSON success' => [200, 'TOP_SECRET', 'contract'];
        yield 'incomplete success' => [200, '{"model":"jev-1.13.0","answers":{},"private":"TOP_SECRET"}', 'contract'];
        $response = WireSamples::response();
        $response['answers']['route']['confidence'] = ['TOP_SECRET'];
        yield 'malformed value before bridge cast' => [200, json_encode($response, \JSON_THROW_ON_ERROR), 'contract'];
    }

    public function testTransportFailureDoesNotPreventTheOtherProviderFromRunning(): void
    {
        $requests = 0;
        $client = new MockHttpClient(static function () use (&$requests): MockResponse {
            if (0 === $requests++) {
                throw new TransportException('TOP_SECRET endpoint failed');
            }

            return new MockResponse(json_encode(WireSamples::response('clm-latest'), \JSON_THROW_ON_ERROR));
        });
        $runs = (new Runner(WireSamples::evaluator($client)))->run(['jev', 'clm'], [self::fixture()], 1);
        self::assertSame('transport', $runs[0]->errorKind);
        self::assertSame('passed', $runs[1]->status());
        self::assertStringNotContainsString('TOP_SECRET', json_encode($runs, \JSON_THROW_ON_ERROR));
    }

    public function testMissingKeyMakesNoRequestAndNeverFallsBackToSyntheticAnswers(): void
    {
        $client = new MockHttpClient(static function (): never { self::fail('Unconfigured provider must not make a request.'); });
        $runs = (new Runner(WireSamples::evaluator($client, '')))->run(['jev'], [self::fixture()], 1);
        self::assertSame('configuration', $runs[0]->errorKind);
        self::assertNull($runs[0]->outcome);
    }

    public function testTimingIncludesDeferredResponseConsumption(): void
    {
        $body = static function (): \Generator {
            usleep(12000);
            yield json_encode(WireSamples::response(), \JSON_THROW_ON_ERROR);
        };
        $runs = (new Runner(WireSamples::evaluator(new MockHttpClient(new MockResponse($body())))))->run(['jev'], [self::fixture()], 1);
        self::assertSame('passed', $runs[0]->status());
        self::assertGreaterThanOrEqual(10.0, $runs[0]->latencyMs);
    }
}
