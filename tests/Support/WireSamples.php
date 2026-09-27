<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\SystemOne\Evaluator;
use App\SystemOne\ProviderRegistry;
use App\SystemOne\ResponseContract;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Factory;
use Symfony\AI\Platform\Bridge\TypeSafe\Jev;
use Symfony\AI\Platform\Bridge\TypeSafe\ModelCatalog;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpClient\MockHttpClient;

/** Synthetic protocol fixtures. These are not model observations or benchmark evidence. */
final class WireSamples
{
    public static function evaluation(): Evaluation
    {
        return new Evaluation('A short public test state.', [
            'urgent' => new NoulQuestion('Is it urgent?'),
            'route' => new ChoiceQuestion('Which capability?', ['worker' => 'Background work', 'check' => 'Validate input']),
            'level' => new ScoreQuestion('How urgent?', ['Low', 'Medium', 'High']),
        ]);
    }

    public static function response(string $model = 'jev-1.13.0'): array
    {
        return [
            'model' => $model,
            'answers' => [
                'urgent' => ['type' => 'noul', 'noul' => 0.05],
                'route' => ['type' => 'choice', 'choice' => 'worker', 'probabilities' => ['worker' => 0.8, 'check' => 0.2], 'confidence' => 0.6],
                'level' => ['type' => 'score', 'score' => 0.4, 'legend' => ['Low', 'Medium', 'High'], 'probabilities' => [0.7, 0.2, 0.1], 'confidence' => 0.55],
            ],
            'usage' => ['input_tokens' => 42, 'output_tokens' => 0],
        ];
    }

    public static function evaluator(MockHttpClient $client, string $key = 'synthetic-key'): Evaluator
    {
        $catalog = new ModelCatalog(['clm-latest' => ['class' => Jev::class, 'capabilities' => []]]);
        $providers = new ProviderRegistry(new ServiceLocator([
            'jev' => static fn () => Factory::createPlatform($key, $client),
            'clm' => static fn () => Factory::createPlatform('', $client, $catalog, baseUrl: 'http://127.0.0.1:8700'),
        ]), 'jev-1.13.0', 'clm-latest', $key, 'http://127.0.0.1:8700');

        return new Evaluator($providers, new ResponseContract());
    }
}
