<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Call\BoundInstruction;
use Deriver\Deriver\Evaluator\Expression\LiteralEvaluator;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\LiteralKind;
use Deriver\Deriver\Sources\SourceRef;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class LiteralEvaluatorTest extends TestCase
{
    public function testEvaluateInterpretsLiteralArraysAndEnumCases(): void
    {
        $evaluator = new LiteralEvaluator();
        self::assertSame(['x' => 2], $evaluator->evaluate(Literal::array(['x' => Literal::constant(2)]))->native());
        $case = $evaluator->evaluate(new Literal(LiteralKind::Enum, 'Color::Red', ['name' => Literal::constant('Red')], ['class' => 'Color']));
        self::assertSame(TermKind::Enum, $case->kind);
        self::assertSame('Red', $case->operands['name']->literal);
        self::assertSame('UNEVALUATED_INITIALIZER', $evaluator->evaluate(Literal::opaque('UNEVALUATED_INITIALIZER'))->literal);
    }
    public function testInstructionPreservesAnEvaluatorBoundInput(): void
    {
        $value = Term::parameter('external');
        $instruction = new BoundInstruction('i', 'constant', new SourceRef('s', 'a.php', 0, 1), constant: $value);
        self::assertNull($instruction->constant);
        self::assertSame($value, (new LiteralEvaluator())->instruction($instruction));
    }
}
