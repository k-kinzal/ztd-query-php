<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Search;

use Deriver\Deriver\Evaluator\Search\Interruption;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class InterruptionTest extends TestCase
{
    public function testReasonDistinguishesUnfinishedWorkFromExternalInputs(): void
    {
        $input = Term::parameter('flag');
        self::assertNull((new Interruption())->reason($input));
        $pending = new Term(TermKind::Deferred, 'call', attributes: ['reason' => 'BUDGET_EXCEEDED']);
        self::assertSame('BUDGET_EXCEEDED', (new Interruption())->reason(new Term(TermKind::Binary, '+', [$pending, $pending])));
    }
    public function testGuardRetainsAnUnfinishedConditionWithoutDiscardingTheValue(): void
    {
        $value = Term::constant(42);
        self::assertSame($value, (new Interruption())->guard($value, Term::parameter('flag')));
        $pending = new Term(TermKind::Deferred, 'condition', attributes: ['reason' => 'BUDGET_EXCEEDED']);
        $result = (new Interruption())->guard($value, $pending);
        self::assertSame(TermKind::ControlledValue, $result->kind);
        self::assertSame([$value, $pending], $result->operands);
    }
}
