<?php

declare(strict_types=1);

namespace App\Tests\SystemOne;

use App\Benchmark\Fixture;
use App\Benchmark\Runner;
use App\SystemOne\Evaluator;
use App\Tests\Support\WireSamples;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BridgeInteroperabilityTest extends KernelTestCase
{
    public function testRealBundleAndLocalFactoryUseTheSameWireContract(): void
    {
        self::bootKernel();
        $requests = [];
        foreach (['jev', 'clm'] as $provider) {
            self::getContainer()->set('system_one.'.$provider.'.client', new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $provider): MockResponse {
                $requests[$provider] = ['method' => $method, 'url' => $url, 'payload' => json_decode($options['body'], true, flags: \JSON_THROW_ON_ERROR)];
                $response = WireSamples::response('jev' === $provider ? 'jev-1.13.0' : 'clm-latest');
                if ('clm' === $provider) {
                    // CLM emits score maps; Jev may emit lists. Both must preserve levels.
                    $response['answers']['level']['legend'] = (object) $response['answers']['level']['legend'];
                    $response['answers']['level']['probabilities'] = (object) $response['answers']['level']['probabilities'];
                }

                return new MockResponse(json_encode($response, \JSON_THROW_ON_ERROR));
            }));
        }
        $evaluator = self::getContainer()->get(Evaluator::class);
        $jev = $evaluator->evaluate('jev', WireSamples::evaluation());
        $clm = $evaluator->evaluate('clm', WireSamples::evaluation());

        self::assertSame('https://api.typesafe.ai/v1/systemone', $requests['jev']['url']);
        self::assertSame('http://127.0.0.1:8700/v1/systemone', $requests['clm']['url']);
        self::assertSame('POST', $requests['jev']['method']);
        self::assertSame('jev-1.13.0', $requests['jev']['payload']['model']);
        self::assertSame('clm-latest', $requests['clm']['payload']['model']);
        unset($requests['jev']['payload']['model'], $requests['clm']['payload']['model']);
        self::assertSame($requests['jev']['payload'], $requests['clm']['payload']);
        self::assertSame(0.05, $jev->answers->getNoul('urgent')->getProbability());
        self::assertSame($jev->answers->getScore('level')->getProbabilities(), $clm->answers->getScore('level')->getProbabilities());
        self::assertSame('clm-latest', $clm->reportedModel);
        self::assertSame(42, $clm->usage->getPromptTokens());
        // Symfony indexes refer to input order, even when the output is sorted differently.
        self::assertSame([1, 0], array_map(static fn ($entry) => $entry->getIndex(), $clm->ranking('route', ['check', 'worker'])->getContent()));
    }

    public function testNumericIdsStayJsonObjectsThroughTheActualBridge(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $payload = json_decode($options['body'], flags: \JSON_THROW_ON_ERROR);
            self::assertInstanceOf(\stdClass::class, $payload->questions);
            self::assertInstanceOf(\stdClass::class, $payload->questions->{'0'}->criteria);

            return new MockResponse('{"model":"clm-latest","answers":{"0":{"type":"choice","choice":"1","probabilities":{"0":0.1,"1":0.9},"confidence":0.8}}}');
        });
        $fixture = new Fixture('numeric-identifiers', 'Synthetic identity test', new Evaluation('State', ['0' => new ChoiceQuestion('Which?', ['0' => 'No', '1' => 'Yes'])]), ['0' => '1']);
        $runs = (new Runner(WireSamples::evaluator($client)))->run(['clm'], [$fixture], 1);
        $outcome = $runs[0]->outcome;
        self::assertSame('1', $outcome->answers->getChoice('0')->getChoice());
        self::assertNull($outcome->usage);
        self::assertSame('1', $runs[0]->jsonSerialize()['rankings']->{'0'}[0]['candidate_id']);
    }
}
