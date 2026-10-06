<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(Precedence::class)]
#[Medium]
final class PrecedenceTest extends TestCase
{
    public function testEdgesAnswersTheLevelAndBothOperandsOfABinaryOperation(): void
    {
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('2'));
        $disjunction = new Binary(BinaryOperator::Or, new IntegerLiteral('1'), new IntegerLiteral('2'));
        $precedence = new Precedence();

        self::assertSame([Precedence::ADDITIVE, $sum->left, $sum->right], $precedence->edges($sum));
        self::assertSame([Precedence::DISJUNCTION, $disjunction->left, $disjunction->right], $precedence->edges($disjunction));
        self::assertNull($precedence->edges(new IntegerLiteral('1')));
        self::assertNull($precedence->edges(new Grouped($sum)));
    }

    public function testEdgesOfAPrefixOperatorHaveNoLeftOperand(): void
    {
        $negation = new Unary(UnaryOperator::Not, new IntegerLiteral('1'));
        $minus = new Unary(UnaryOperator::Minus, new IntegerLiteral('1'));
        $precedence = new Precedence();

        self::assertSame([Precedence::NEGATION, null, $negation->operand], $precedence->edges($negation));
        self::assertSame([Precedence::PREFIX, null, $minus->operand], $precedence->edges($minus));
    }

    public function testEdgesOfAPostfixOperatorHaveNoRightOperand(): void
    {
        $collate = new Collate(new IntegerLiteral('1'), new Name('nocase'));
        $test = new NullTest(new IntegerLiteral('1'), NullTestForm::IsNull);
        $list = new InList(new IntegerLiteral('1'), []);
        $query = new InQuery(new IntegerLiteral('1'), new Select([new ResultColumn(new IntegerLiteral('2'))]));
        $table = new InTable(new IntegerLiteral('1'), new QualifiedName(new Name('t')));
        $precedence = new Precedence();

        self::assertSame([Precedence::COLLATION, $collate->operand, null], $precedence->edges($collate));
        self::assertSame([Precedence::EQUALITY, $test->operand, null], $precedence->edges($test));
        self::assertSame([Precedence::EQUALITY, $list->operand, null], $precedence->edges($list));
        self::assertSame([Precedence::EQUALITY, $query->operand, null], $precedence->edges($query));
        self::assertSame([Precedence::EQUALITY, $table->operand, null], $precedence->edges($table));
    }

    public function testEdgesOfAMatchEndWithTheEscapeWhenWritten(): void
    {
        $plain = new PatternMatch(PatternOperator::Like, new TextLiteral('a'), new TextLiteral('b'));
        $escaped = new PatternMatch(PatternOperator::Like, new TextLiteral('a'), new TextLiteral('b'), false, new TextLiteral('!'));
        $between = new Between(new IntegerLiteral('1'), new IntegerLiteral('0'), new IntegerLiteral('9'));
        $precedence = new Precedence();

        self::assertSame([Precedence::EQUALITY, $plain->left, $plain->right], $precedence->edges($plain));
        self::assertSame([Precedence::EQUALITY, $escaped->left, $escaped->escape], $precedence->edges($escaped));
        self::assertSame([Precedence::EQUALITY, $between->operand, $between->high], $precedence->edges($between));
    }

    public function testOpeningIsTheWeakestLevelOnTheLeftEdgeAndClosedBeforeAPrefixOperator(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 + 2 * 3, NOT a = b, 1 = 2 + 3, (1 + 2) * 3, -a + 1, a COLLATE nocase, 1 ISNULL, -a, 1, a LIKE b ESCAPE 'x', 1 + -2, NOT 1 + 2 FROM t");
        $precedence = new Precedence();
        $levels = array_map(static fn (Field $field): ?int => $field->expression === null ? null : $precedence->opening($field->expression), $query->fields()->items ?? []);

        self::assertSame([
            Precedence::ADDITIVE, Precedence::CLOSED, Precedence::EQUALITY, Precedence::MULTIPLICATIVE, Precedence::ADDITIVE, Precedence::COLLATION,
            Precedence::EQUALITY, Precedence::CLOSED, Precedence::CLOSED, Precedence::EQUALITY, Precedence::ADDITIVE, Precedence::CLOSED,
        ], $levels);
    }

    public function testClosingIsTheWeakestLevelOnTheRightEdgeAndClosedAfterAPostfixOperator(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 + 2 * 3, NOT a = b, 1 = 2 + 3, (1 + 2) * 3, -a + 1, a COLLATE nocase, 1 ISNULL, -a, 1, a LIKE b ESCAPE 'x', 1 + -2, NOT 1 + 2, 1 * 2 + 3 FROM t");
        $precedence = new Precedence();
        $levels = array_map(static fn (Field $field): ?int => $field->expression === null ? null : $precedence->closing($field->expression), $query->fields()->items ?? []);

        self::assertSame([
            Precedence::ADDITIVE, Precedence::NEGATION, Precedence::EQUALITY, Precedence::MULTIPLICATIVE, Precedence::ADDITIVE, Precedence::CLOSED,
            Precedence::CLOSED, Precedence::PREFIX, Precedence::CLOSED, Precedence::EQUALITY, Precedence::ADDITIVE, Precedence::NEGATION, Precedence::ADDITIVE,
        ], $levels);
    }

    public function testTakesEscapeFindsAPatternMatchWithoutEscapeOnTheRightEdge(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT a LIKE b, a LIKE b ESCAPE 'x', NOT a LIKE b, a AND b LIKE 1, a LIKE b AND 1, 1, a LIKE b ESCAPE 'x' AND c GLOB d, (a LIKE b) FROM t");
        $precedence = new Precedence();
        $takes = array_map(static fn (Field $field): ?bool => $field->expression === null ? null : $precedence->takesEscape($field->expression), $query->fields()->items ?? []);

        self::assertSame([true, false, true, true, false, false, true, false], $takes);
    }
}
