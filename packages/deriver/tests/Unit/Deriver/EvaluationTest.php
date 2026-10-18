<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver;

use Deriver\Deriver\Evaluation as Subject;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class EvaluationTest extends TestCase
{
    public function testInputDemandsOnlyTheSelectedOperand(): void
    {
        $seen = [];
        $request = new Subject(EvaluationOperation::Binary, '+', new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 3), static function (int|string $key) use (&$seen): Term {
            $seen[] = $key;
            return Term::constant($key);
        });
        self::assertSame(1, $request->input(1)->native());
        self::assertSame([1], $seen);
    }

    public function testTruthSupportsSuppliedAndStandaloneBooleanDemands(): void
    {
        $source = new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 3);
        $standalone = new Subject(EvaluationOperation::Branch, '', $source, static fn (): Term => Term::constant('0'));
        $separate = new Subject(EvaluationOperation::Branch, '', $source, static fn (): Term => Term::constant(99), static fn (): Term => Term::constant(false));
        self::assertSame(false, $standalone->truth(0)->native());
        self::assertSame(false, $separate->truth(0)->native());
        self::assertSame(99, $separate->input(0)->native());
    }

}
