<?php

declare(strict_types=1);

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RunCommandTest extends KernelTestCase
{
    private function command(MockHttpClient $client): CommandTester
    {
        self::bootKernel();
        self::getContainer()->set('system_one.jev.client', $client);
        self::getContainer()->set('system_one.clm.client', $client);

        return new CommandTester((new Application(self::$kernel))->find('app:system-one:run'));
    }

    public function testListMakesNoNetworkRequest(): void
    {
        $command = $this->command(new MockHttpClient(static function (): never { self::fail('Listing must be offline.'); }));
        self::assertSame(0, $command->execute(['--list' => true]));
        self::assertStringContainsString('typed.calm', $command->getDisplay());
    }

    public function testInvalidCaseIsRejectedBeforeNetworkAccess(): void
    {
        $command = $this->command(new MockHttpClient(static function (): never { self::fail('Invalid arguments must not cause network access.'); }));
        self::assertSame(2, $command->execute(['--case' => ['missing-fixture']]));
    }

    public function testJsonReportIsParseableAndRecordsBothProvidersAndStableIds(): void
    {
        $payloads = [];
        $command = $this->command(new MockHttpClient(static function (string $method, string $url, array $options) use (&$payloads): MockResponse {
            $payload = json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR);
            $payloads[] = $payload;
            self::assertSame(['model', 'state', 'questions'], array_keys($payload));
            self::assertArrayNotHasKey('expected', $payload);

            // A hand-authored protocol response, never generated from fixture expectations.
            return new MockResponse(json_encode([
                'model' => $payload['model'],
                'answers' => ['capability' => [
                    'type' => 'choice', 'choice' => 'messenger', 'confidence' => 0.88,
                    'probabilities' => ['messenger' => 0.9, 'voter' => 0.02, 'validator' => 0.02, 'mercure' => 0.02, 'serializer' => 0.02, 'none' => 0.02],
                ]],
            ], \JSON_THROW_ON_ERROR));
        }));
        $path = self::getContainer()->getParameter('kernel.project_dir').'/var/test-report.json';
        try {
            self::assertSame(0, $command->execute(['--case' => ['capability.background'], '--repeat' => 2, '--format' => 'json', '--output' => $path, '--runtime-label' => '<info>literal deployment label</info>']));
            $report = json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
            self::assertCount(4, $report['runs']);
            self::assertSame(['jev', 'clm', 'clm', 'jev'], array_column($report['runs'], 'provider'));
            self::assertCount(1, array_unique(array_column($report['runs'], 'request_sha256')));
            self::assertSame('messenger', $report['runs'][0]['rankings']['capability'][0]['candidate_id']);
            self::assertCount(6, $report['runs'][0]['rankings']['capability']);
            self::assertSame(2, $report['summary']['jev']['compatible']);
            self::assertSame($report, json_decode(file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testProviderFailureCreatesAReportAndReturnsNonzero(): void
    {
        $command = $this->command(new MockHttpClient(new MockResponse('{"detail":"API_KEY_MUST_NOT_APPEAR"}', ['http_code' => 401])));
        self::assertSame(2, $command->execute(['--provider' => ['jev'], '--case' => ['capability.background'], '--format' => 'json']));
        $report = json_decode($command->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('not_evaluated', $report['runs'][0]['behavior']);
        self::assertSame(1, $report['summary']['jev']['errors']);
        self::assertStringNotContainsString('API_KEY_MUST_NOT_APPEAR', $command->getDisplay());
    }
}
