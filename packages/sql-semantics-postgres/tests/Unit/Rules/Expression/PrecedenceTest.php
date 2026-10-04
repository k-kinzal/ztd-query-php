<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Precedence::class)]
#[Small]
final class PrecedenceTest extends TestCase
{
    public function testOperatorAnswersTheLevelOfAnOperatorName(): void
    {
        $precedence = new Precedence();
        self::assertSame(
            [Precedence::ADDITIVE, Precedence::UNARY, Precedence::COMPARISON, Precedence::OPERATOR, Precedence::OPERATOR],
            [$precedence->operator(new OperatorName(new Name('-'))), $precedence->operator(new OperatorName(new Name('-')), true), $precedence->operator(new OperatorName(new Name('<='))), $precedence->operator(new OperatorName(new Name('@@'))), $precedence->operator(new OperatorName(new Name('+'), [], true))],
        );
    }

    public function testEdgesAnswersTheOperandsAndLevels(): void
    {
        $left = new NullLiteral();
        $right = new NullLiteral();
        self::assertSame([$left, Precedence::AND, $right, Precedence::AND], (new Precedence())->edges(new BooleanOperation(BooleanOperator::And, $left, $right)));
    }

    public function testOperatorsAnswersNullForAPredicate(): void
    {
        self::assertNull((new Precedence())->operators(new NullTest(new NullLiteral(), false)));
    }

    public function testPredicatesAnswersTheEdgesOfANullTest(): void
    {
        $operand = new NullLiteral();
        self::assertSame([$operand, Precedence::IS, null, Precedence::CLOSED], (new Precedence())->predicates(new NullTest($operand, false)));
    }

    public function testPrimaryTellsAConstantFromAnOperatorForm(): void
    {
        self::assertSame([true, false], [(new Precedence())->primary(new NullLiteral()), (new Precedence())->primary(new DefaultRequest())]);
    }

    public function testRestrictedTellsBExprForms(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new NullLiteral(), new UnaryOperation(new OperatorName(new Name('-')), new NullLiteral()));
        self::assertSame([true, false], [(new Precedence())->restricted($sum), (new Precedence())->restricted(new NullTest(new NullLiteral(), false))]);
    }

    public function testOpeningIsTheWeakestTokenOnTheLeftEdge(): void
    {
        $test = new NullTest(new BinaryOperation(new OperatorName(new Name('*')), new NullLiteral(), new NullLiteral()), false);
        self::assertSame(Precedence::IS, (new Precedence())->opening($test));
    }

    public function testClosingIsTheWeakestRuleOnTheRightEdge(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new NullLiteral(), new Negation(new NullLiteral()));
        self::assertSame(Precedence::NOT, (new Precedence())->closing($sum));
    }

    public function testBeforeTellsWhetherAnOperandKeepsItsPlaceBeforeAToken(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new NullLiteral(), new NullLiteral());
        self::assertSame([true, false], [(new Precedence())->before($sum, Precedence::ADDITIVE), (new Precedence())->before($sum, Precedence::MULTIPLICATIVE)]);
    }

    public function testAfterTellsWhetherAnOperandKeepsItsPlaceAfterAToken(): void
    {
        $sum = new BinaryOperation(new OperatorName(new Name('+')), new NullLiteral(), new NullLiteral());
        self::assertSame([false, true, true], [(new Precedence())->after($sum, Precedence::ADDITIVE), (new Precedence())->after($sum, Precedence::COMPARISON), (new Precedence())->after(new Negation(new NullLiteral()), Precedence::UNARY)]);
    }

    public function testTakesEscapeFindsAnOpenPatternMatchOnTheRightEdge(): void
    {
        $open = new Negation(new PatternMatch(new NullLiteral(), PatternOperator::ILike, false, new NullLiteral()));
        self::assertSame([true, false], [(new Precedence())->takesEscape($open), (new Precedence())->takesEscape(new Negation(new NullLiteral()))]);
    }
}
