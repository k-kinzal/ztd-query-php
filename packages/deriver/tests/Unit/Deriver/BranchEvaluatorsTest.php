<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver;

use Deriver\Deriver\BranchEvaluators;
use Deriver\Deriver\Evaluation;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class BranchEvaluatorsTest extends TestCase
{
    public function testTruthRequestsOnlyBooleanCandidates(): void
    {
        $values = 0;
        $truths = 0;
        $evaluation = new Evaluation(EvaluationOperation::Branch, '', new SourceRef('s', 'a.php', 0, 1), static function () use (&$values): Term {
            ++$values;
            return Term::constant(99);
        }, static function () use (&$truths): Term {
            ++$truths;
            return Term::constant(false);
        });
        $evaluator = BranchEvaluators::truth(10)[0];
        self::assertSame(false, $evaluator->evaluate($evaluation)?->native());
        self::assertSame(10, $evaluator->priority);
        self::assertSame(0, $values);
        self::assertSame(1, $truths);
    }
}
