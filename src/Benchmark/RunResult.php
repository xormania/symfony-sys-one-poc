<?php

declare(strict_types=1);

namespace App\Benchmark;

use App\SystemOne\EvaluationOutcome;
use Symfony\AI\Platform\Bridge\TypeSafe\Answer\ChoiceAnswer;

final readonly class RunResult implements \JsonSerializable
{
    /** @param list<string> $behaviorFailures */
    public function __construct(
        public string $provider,
        public Fixture $fixture,
        public int $iteration,
        public float $latencyMs,
        public ?EvaluationOutcome $outcome = null,
        public array $behaviorFailures = [],
        public ?string $errorKind = null,
        public ?string $errorMessage = null,
    ) {
    }

    public function status(): string
    {
        return null !== $this->errorKind ? $this->errorKind.'_error' : ([] === $this->behaviorFailures ? 'passed' : 'behavior_failed');
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $payload = $this->fixture->evaluation->jsonSerialize();
        $rankings = [];
        if (null !== ($outcome = $this->outcome)) {
            foreach ($outcome->answers->all() as $id => $answer) {
                if (!$answer instanceof ChoiceAnswer) {
                    continue;
                }
                $criteria = $payload['questions'][$id]['criteria'];
                if (!\is_array($criteria)) {
                    throw new \LogicException('Choice question requires a candidate map.');
                }
                $ids = array_map(strval(...), array_keys($criteria));
                $entries = [];
                foreach ($outcome->ranking((string) $id, $ids)->getContent() as $entry) {
                    $entries[] = ['candidate_id' => $ids[$entry->getIndex()], 'score' => $entry->getScore()];
                }
                $rankings[$id] = $entries;
            }
        }

        return [
            'provider' => $this->provider,
            'fixture' => $this->fixture->id,
            'iteration' => $this->iteration,
            'request_sha256' => hash('sha256', json_encode($payload, \JSON_THROW_ON_ERROR)),
            'latency_ms' => round($this->latencyMs, 3),
            'contract' => null !== $this->outcome ? 'passed' : ('contract' === $this->errorKind ? 'failed' : 'not_evaluated'),
            'behavior' => null === $this->outcome ? 'not_evaluated' : ([] === $this->behaviorFailures ? 'passed' : 'failed'),
            'behavior_failures' => $this->behaviorFailures,
            'error' => null === $this->errorKind ? null : ['kind' => $this->errorKind, 'message' => $this->errorMessage],
            'evaluation' => $this->outcome,
            'rankings' => (object) $rankings,
        ];
    }
}
