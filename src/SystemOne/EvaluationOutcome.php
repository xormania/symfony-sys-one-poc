<?php

declare(strict_types=1);

namespace App\SystemOne;

use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ChoiceAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\NoulAnswer;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ScoreAnswer;
use Symfony\AI\Platform\Reranking\RerankingEntry;
use Symfony\AI\Platform\Result\RerankingResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

final readonly class EvaluationOutcome implements \JsonSerializable
{
    public function __construct(
        public Answers $answers,
        public string $requestedModel,
        public string $reportedModel,
        public ?TokenUsageInterface $usage,
    ) {
    }

    /** @param list<string> $candidateIds */
    public function ranking(string $questionId, array $candidateIds): RerankingResult
    {
        $probabilities = $this->answers->getChoice($questionId)->getProbabilities();
        if (\count(array_unique($candidateIds)) !== \count($candidateIds) || \count($candidateIds) !== \count($probabilities) || [] !== array_diff($candidateIds, array_map(strval(...), array_keys($probabilities)))) {
            throw new \InvalidArgumentException('Ranking candidate IDs must exactly match the Choice answer.');
        }
        $entries = [];
        foreach ($candidateIds as $index => $id) {
            $entries[] = new RerankingEntry($index, $probabilities[$id]);
        }
        usort($entries, static fn (RerankingEntry $a, RerankingEntry $b): int => $b->getScore() <=> $a->getScore() ?: $a->getIndex() <=> $b->getIndex());

        return new RerankingResult($entries);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $answers = [];
        foreach ($this->answers->all() as $id => $answer) {
            $answers[$id] = match (true) {
                $answer instanceof NoulAnswer => ['type' => 'noul', 'value' => $answer->getProbability()],
                $answer instanceof ChoiceAnswer => [
                    'type' => 'choice', 'value' => $answer->getChoice(),
                    'probabilities' => (object) $answer->getProbabilities(), 'confidence' => $answer->getConfidence(),
                ],
                $answer instanceof ScoreAnswer => [
                    'type' => 'score', 'value' => $answer->getScore(),
                    'probabilities' => $answer->getProbabilities(), 'confidence' => $answer->getConfidence(), 'legend' => $answer->getLegend(),
                ],
                default => throw new \LogicException('Unsupported typed answer.'),
            };
        }

        return [
            'requested_model' => $this->requestedModel,
            'reported_model' => $this->reportedModel,
            'answers' => (object) $answers,
            'provider_reported_usage' => [
                'input_tokens' => $this->usage?->getPromptTokens(),
                'output_tokens' => $this->usage?->getCompletionTokens(),
            ],
        ];
    }
}
