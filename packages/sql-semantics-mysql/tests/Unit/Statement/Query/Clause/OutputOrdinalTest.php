<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OutputOrdinal::class)]
#[Medium]
final class OutputOrdinalTest extends TestCase
{
    public function testPositionAnswersTheWrittenPosition(): void
    {
        self::assertSame(2, (new OutputOrdinal(new NumberLiteral('2')))->position());
        self::assertSame(3, (new OutputOrdinal(new NumberLiteral('003')))->position());
        self::assertSame(PHP_INT_MAX, (new OutputOrdinal(new NumberLiteral('9999999999999999999')))->position());
    }

    public function testNamePreservesLegacyBooleanPositions(): void
    {
        $true = new OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(true));
        $false = new OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(false));
        self::assertSame(['TRUE', 1, 'FALSE', 0], [$true->name(), $true->position(), $false->name(), $false->position()]);
        self::assertSame('02', (new OutputOrdinal(new NumberLiteral('02')))->name());
    }

    public function testLiteralRetainsTheIntegerAndItsWrappers(): void
    {
        $literal = new NumberLiteral('02');
        $expression = new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus, $literal));
        $ordinal = new OutputOrdinal($expression);
        self::assertSame(2, $ordinal->position());
        self::assertSame($expression, $ordinal->expression);
        self::assertSame($literal, $ordinal->literal());
    }

    public function testANegativeOperandIsRejected(): void
    {
        $this->expectExceptionMessage('A select list position is an unsigned integer.');
        new OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, new NumberLiteral('2')));
    }

    public function testAGroupedDecimalIsRejected(): void
    {
        $this->expectExceptionMessage('A select list position is an unsigned integer.');
        new OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new NumberLiteral('2.0')));
    }

    public function testDeriveScalarTakesTheFactsOfTheField(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT 1, a FROM t GROUP BY 2 ORDER BY 2', [$table]);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        $fact = $operation->query->orderBy[0]->expression->scalarInformation();
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($operation->field(1), $fact->resolution->field);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveScalarReportsAPositionOutsideTheSelectList(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $operation = $semantics->analyze('SELECT * FROM t ORDER BY 3', [$table]);

        self::assertCount(1, $operation->diagnostics());
        self::assertInstanceOf(OrdinalOutOfRange::class, $operation->diagnostics()[0]);
    }

    public function testDeriveScalarDependsOnAnOpenStar(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM t ORDER BY 2');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertInstanceOf(Dependent::class, $operation->query->orderBy[0]->expression->scalarInformation()->type);
        self::assertSame([], $operation->diagnostics());
    }

    public function testRenderWritesTheInteger(): void
    {
        self::assertSame('SELECT a FROM t ORDER BY 1 DESC', (new Semantics(Dialect::MySql))->analyze('select a from t order by 1 desc')->toString());
    }

    public function testADecimalIsRejected(): void
    {
        $this->expectExceptionMessage('A select list position is an unsigned integer.');

        new OutputOrdinal(new NumberLiteral('1.5'));
    }
}
