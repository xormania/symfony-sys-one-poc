<?php

declare(strict_types=1);

namespace App\SystemOne;

use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;

/** Checks response semantics that successful HTTP/bridge conversion alone cannot establish. */
final class ResponseContract
{
    private const float TOLERANCE = 0.001;

    /** @param array<string, mixed> $response */
    public function verify(Evaluation $evaluation, array $response): void
    {
        if (!\is_string($response['model'] ?? null) || '' === trim($response['model'])) {
            throw new \UnexpectedValueException('Response must identify the answering model.');
        }

        $questions = $evaluation->jsonSerialize()['questions'];
        $answers = $response['answers'] ?? null;
        if (!\is_array($answers) || !$this->sameKeys($questions, $answers)) {
            throw new \UnexpectedValueException('Response question IDs must exactly match the request.');
        }

        foreach ($questions as $id => $question) {
            $answer = $answers[$id];
            $type = $question['type'];
            if (!\is_array($answer) || ($answer['type'] ?? null) !== $type) {
                throw new \UnexpectedValueException('Answer type must match the requested question.');
            }
            if ('noul' === $type) {
                $this->number($answer['noul'] ?? null, 0, 1);
                continue;
            }

            $criteria = $question['criteria'] ?? null;
            $probabilities = $answer['probabilities'] ?? null;
            if (!\is_array($criteria) || !\is_array($probabilities) || [] === $probabilities || !$this->sameKeys($criteria, $probabilities)) {
                throw new \UnexpectedValueException('Distribution must contain every candidate exactly once.');
            }
            $sum = 0.0;
            $numbers = [];
            foreach ($probabilities as $candidate => $probability) {
                $numbers[$candidate] = $this->number($probability, 0, 1);
                $sum += $numbers[$candidate];
            }
            if (abs(1.0 - $sum) > self::TOLERANCE) {
                throw new \UnexpectedValueException('Distribution must sum to one within rounding tolerance.');
            }
            $this->number($answer['confidence'] ?? null, 0, 1);

            if ('choice' === $type) {
                $choice = $answer['choice'] ?? null;
                if (!\is_string($choice) || !\array_key_exists($choice, $criteria)) {
                    throw new \UnexpectedValueException('Selected candidate must retain a requested candidate ID.');
                }
                if (max($numbers) - $numbers[$choice] > self::TOLERANCE) {
                    throw new \UnexpectedValueException('Selected candidate must have maximal probability.');
                }
                continue;
            }

            $legend = $answer['legend'] ?? null;
            if (!\is_array($legend) || !$this->sameKeys($criteria, $legend)) {
                throw new \UnexpectedValueException('Score legend must preserve the requested levels.');
            }
            foreach ($criteria as $level => $description) {
                if ($legend[$level] !== $description) {
                    throw new \UnexpectedValueException('Score legend changed a level description.');
                }
            }
            $score = $this->number($answer['score'] ?? null, 0, \count($criteria) - 1);
            $expected = 0.0;
            foreach ($numbers as $level => $probability) {
                $expected += (int) $level * $probability;
            }
            if (abs($score - $expected) > self::TOLERANCE) {
                throw new \UnexpectedValueException('Score must equal its probability-weighted level.');
            }
        }
    }

    private function number(mixed $value, float $min, float $max): float
    {
        if ((!\is_int($value) && !\is_float($value)) || !is_finite((float) $value) || $value < $min || $value > $max) {
            throw new \UnexpectedValueException('Response contains an invalid numeric value.');
        }

        return (float) $value;
    }

    /** @param array<array-key, mixed> $first
     * @param array<array-key, mixed> $second
     */
    private function sameKeys(array $first, array $second): bool
    {
        return \count($first) === \count($second) && [] === array_diff_key($first, $second);
    }
}
