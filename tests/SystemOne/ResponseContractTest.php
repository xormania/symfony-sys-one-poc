<?php

declare(strict_types=1);

namespace App\Tests\SystemOne;

use App\SystemOne\ResponseContract;
use App\Tests\Support\WireSamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseContractTest extends TestCase
{
    #[DataProvider('invalidResponses')]
    public function testRejectsProtocolViolationsBeforeCoercion(\Closure $mutate): void
    {
        $response = WireSamples::response();
        $mutate($response);
        $this->expectException(\UnexpectedValueException::class);
        (new ResponseContract())->verify(WireSamples::evaluation(), $response);
    }

    public static function invalidResponses(): iterable
    {
        yield 'missing model' => [static function (array &$r): void { unset($r['model']); }];
        yield 'missing answer' => [static function (array &$r): void { unset($r['answers']['urgent']); }];
        yield 'unexpected answer' => [static function (array &$r): void { $r['answers']['extra'] = ['type' => 'noul', 'noul' => 0.5]; }];
        yield 'wrong type' => [static function (array &$r): void { $r['answers']['urgent']['type'] = 'score'; }];
        yield 'numeric string' => [static function (array &$r): void { $r['answers']['urgent']['noul'] = '0.1'; }];
        yield 'out of range' => [static function (array &$r): void { $r['answers']['urgent']['noul'] = 1.01; }];
        yield 'non-finite' => [static function (array &$r): void { $r['answers']['urgent']['noul'] = \NAN; }];
        yield 'missing candidate' => [static function (array &$r): void { unset($r['answers']['route']['probabilities']['check']); }];
        yield 'candidate identity' => [static function (array &$r): void { $r['answers']['route']['choice'] = 'invented'; }];
        yield 'wrong winner' => [static function (array &$r): void { $r['answers']['route']['choice'] = 'check'; }];
        yield 'bad distribution sum' => [static function (array &$r): void { $r['answers']['route']['probabilities']['worker'] = 0.1; }];
        yield 'bad confidence' => [static function (array &$r): void { $r['answers']['route']['confidence'] = ['invalid']; }];
        yield 'changed score legend' => [static function (array &$r): void { $r['answers']['level']['legend'][0] = 'High'; }];
        yield 'inconsistent expected score' => [static function (array &$r): void { $r['answers']['level']['score'] = 1.9; }];
    }
}
