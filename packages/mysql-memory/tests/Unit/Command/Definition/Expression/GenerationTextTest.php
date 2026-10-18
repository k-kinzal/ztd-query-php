<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\GenerationText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(GenerationText::class)]
#[Small]
final class GenerationTextTest extends TestCase
{
    public function testTailUsesTheStructuralQueryBoundaryInsteadOfLiteralSelectText(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\StringLiteral(['select 1']), new M\Expression\Subquery\ScalarSubquery($query));
        self::assertSame('select 1)))', (new GenerationText())->tail($expression));
        self::assertSame('select 1', (new GenerationText())->query($query));
        self::assertSame([['(', false], ['select 1', true], [')', false]], (new GenerationText())->parts(new M\Expression\Subquery\ScalarSubquery($query)));
        self::assertSame([['(', false], ['1', false], [',', false], ['select 1', true], [')', false]], (new GenerationText())->joined([[['1', false]], [['select 1', true]]], '(', ',', ')'));
    }
    public function testTailUnwrapsNamedCallArgumentOperands(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $call = new M\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ABS'), [new M\Call\CallArgument(new M\Expression\Subquery\ScalarSubquery($query))]);
        self::assertSame('select 1)))', (new GenerationText())->tail($call));
    }

    public function testPartsRetainsGroupingAroundAQuery(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        self::assertSame([['(', false], ['select 1', true], [')', false]], (new GenerationText())->parts(new M\Expression\Subquery\ScalarSubquery($query)));
    }

    public function testJoinedPreservesMarkedChildPositions(): void
    {
        self::assertSame([['(', false], ['1', false], [',', false], ['select 1', true], [')', false]], (new GenerationText())->joined([[['1', false]], [['select 1', true]]], '(', ',', ')'));
    }

    public function testQueryKeepsQuotedIdentifiersAndCompactSeparators(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1')), new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]);
        self::assertSame('select 1,2', (new GenerationText())->query($query));
    }
    public function testWrappedDistinguishesVariablesFromOrdinaryLeaves(): void
    {
        self::assertSame([['(@`v`)', false]], (new GenerationText())->wrapped(new M\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('v'))));
        self::assertNull((new GenerationText())->wrapped(new M\Literal\NumberLiteral('1')));
    }

}
