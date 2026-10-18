<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\ActionKind as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ActionKindTest extends TestCase
{
    public function testThrowActionsLowerToExceptionalControlFlow(): void
    {
        $compiler = new \Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler(new \Deriver\Deriver\Sources\SourceRef('s', 'evaluator:custom', 0, 1));
        $action = new \Deriver\Deriver\Evaluator\Call\Plan\Action(Subject::Throw, [\Deriver\Deriver\Evaluator\Call\Plan\Expression::literal(\Deriver\Deriver\Evaluator\Term::opaque('vendor.failure', 'Throwable'))]);
        self::assertTrue((new \Deriver\Deriver\Evaluator\Call\Plan\PlanActions($compiler))->apply($action));
        self::assertSame(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Throw, $compiler->terminators[0]->kind);
        self::assertSame('vendor.failure', (new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->instruction($compiler->instructions[0][0])->literal);
    }
}
