<?php

declare(strict_types=1);

namespace App\SystemOne;

use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final readonly class Evaluator
{
    public function __construct(private ProviderRegistry $providers, private ResponseContract $contract)
    {
    }

    public function evaluate(string $provider, Evaluation $evaluation): EvaluationOutcome
    {
        $model = $this->providers->model($provider);
        $result = $this->providers->platform($provider)->invoke($model, $evaluation);

        // Invoke is lazy. Materialize inside the measured call so network and conversion count.
        // Let Symfony translate HTTP errors, but validate successful wire values before casts.
        $rawResult = $result->getRawResult();
        if ($rawResult instanceof RawHttpResult && 200 !== $rawResult->getObject()->getStatusCode()) {
            $result->asObject();
        }
        $raw = $rawResult->getData();
        $this->contract->verify($evaluation, $raw);
        $answers = $result->asObject();
        if (!$answers instanceof Answers) {
            throw new \UnexpectedValueException('Provider did not return TypeSafe typed answers.');
        }

        $reportedModel = $raw['model'];
        if (!\is_string($reportedModel)) {
            throw new \UnexpectedValueException('Response model must be a string.');
        }
        $usage = $result->getMetadata()->get('token_usage');

        return new EvaluationOutcome($answers, $model, $reportedModel, $usage instanceof TokenUsageInterface ? $usage : null);
    }
}
