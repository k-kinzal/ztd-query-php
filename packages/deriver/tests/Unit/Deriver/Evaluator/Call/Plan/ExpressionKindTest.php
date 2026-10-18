<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind as Subject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ExpressionKindTest extends TestCase
{
    public function testIntrinsicExpressionsRetainRegisteredOperationNames(): void
    {
        $compiler = new \Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler(new \Deriver\Deriver\Sources\SourceRef('s', 'evaluator:custom', 0, 1));
        $register = $compiler->expression(new \Deriver\Deriver\Evaluator\Call\Plan\Expression(Subject::Intrinsic, 'vendor.custom-intrinsic'));
        self::assertSame('intrinsic', $compiler->instructions[0][0]->operation);
        self::assertSame('vendor.custom-intrinsic', $compiler->instructions[0][0]->name);
        self::assertSame($register, $compiler->instructions[0][0]->result);
    }
}
