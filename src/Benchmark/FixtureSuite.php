<?php

declare(strict_types=1);

namespace App\Benchmark;

use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;

/** A deliberately small, public corpus. Expectations never enter an Evaluation. */
final class FixtureSuite
{
    /** @return list<Fixture> */
    public function all(): array
    {
        $capabilities = [
            'messenger' => 'Symfony Messenger: dispatch work for asynchronous processing by background workers.',
            'voter' => 'Symfony Security voter: decide whether a user may perform an action on a particular resource.',
            'validator' => 'Symfony Validator: check input constraints and return field-level violations.',
            'mercure' => 'Mercure: push server events to subscribed browsers over server-sent events.',
            'serializer' => 'Symfony Serializer: convert objects to and from JSON or other representations.',
            'none' => 'None of these capabilities supplies the requested behavior.',
        ];
        $states = [
            'background' => ['Generate a report taking three minutes. Return immediately and perform the work in a separate background worker.', 'messenger'],
            'authorization' => ['A document belongs to another user. Decide whether the signed-in user may edit this specific document.', 'voter'],
            'validation' => ['Reject a form whose email address is malformed and show a validation error beside the email field.', 'validator'],
            'push' => ['A job has finished. Push that event from the server to all subscribed browser tabs using server-sent events.', 'mercure'],
            'missing-capability' => ['Decode a JPEG, crop the image, resample its pixels and encode a thumbnail. Choose a capability that actually processes image pixels.', 'none'],
        ];
        $fixtures = [];
        foreach ($states as $id => [$state, $winner]) {
            $fixtures[] = new Fixture('capability.'.$id, 'Rank bounded capability descriptions, including an explicit missing-capability option.', new Evaluation($state, [
                'capability' => new ChoiceQuestion('Which capability directly supplies the requested behavior?', $capabilities),
            ]), ['capability' => $winner]);
        }
        $fixtures[] = new Fixture('capability.reordered', 'The background-worker answer should survive reversal of candidate order.', new Evaluation($states['background'][0], [
            'capability' => new ChoiceQuestion('Which capability directly supplies the requested behavior?', array_reverse($capabilities, true)),
        ]), ['capability' => 'messenger']);

        // Intentionally use the short rubric from CLM issue #3, to make the reported failure visible.
        $questions = [
            'angry' => new NoulQuestion('Is the customer angry?'),
            'tone' => new ChoiceQuestion('What is the customer expressing?', [
                'calm' => 'The customer is calm and satisfied.',
                'angry' => 'The customer is angry and upset.',
            ]),
            'frustration' => new ScoreQuestion('How frustrated is the customer?', ['Calm', 'Frustrated', 'Very angry']),
        ];
        $fixtures[] = new Fixture('typed.calm', 'All three primitives should reflect a satisfied customer; reproduces the CLM score concern.', new Evaluation('Customer: Thanks for the quick help yesterday, everything works now. Have a nice day!', $questions), [
            'angry' => ['min' => 0.0, 'max' => 0.4], 'tone' => 'calm', 'frustration' => ['min' => 0.0, 'max' => 0.75],
        ]);
        $fixtures[] = new Fixture('typed.angry', 'The same questions should change answers when the state changes.', new Evaluation('Customer: This is the THIRD time I have called. You charged me twice, ignored every request, and I am furious!', $questions), [
            'angry' => ['min' => 0.6, 'max' => 1.0], 'tone' => 'angry', 'frustration' => ['min' => 1.25, 'max' => 2.0],
        ]);

        return $fixtures;
    }

    public function hash(): string
    {
        return hash('sha256', json_encode($this->all(), \JSON_THROW_ON_ERROR));
    }
}
