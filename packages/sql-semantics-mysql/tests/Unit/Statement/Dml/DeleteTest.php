<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Delete::class)]
#[Medium]
final class DeleteTest extends TestCase
{
    public function testValueRejectsAnOffsetInTheRowLimit(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('DELETE FROM t')->statement;
        self::assertInstanceOf(Delete::class, $statement);
        $limit = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('DELETE limits rows without an offset.');
        new Delete($statement->with, $statement->options, $statement->table, limit: $limit);
    }

    public function testValueRejectsAnOutputPositionInWriteOrdering(): void
    {
        $this->expectExceptionMessage('DELETE ordering uses expressions');

        new Delete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new QualifiedName(new Name('t'))), orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')))]);
    }

    public function testDeriveStatementSeesTheTableUnderItsAlias(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('DELETE FROM t AS x WHERE x.a = 1 ORDER BY b LIMIT 1', [$t]);
        self::assertInstanceOf(Delete::class, $operation->statement);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('DELETE LOW_PRIORITY QUICK IGNORE FROM t PARTITION (p) WHERE a = 1 ORDER BY b LIMIT 5', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('delete low_priority quick ignore from t partition (p) where a = 1 order by b limit 5')->toString());
    }

    public function testRenderWritesTheHintCommentAfterTheVerb(): void
    {
        self::assertSame('DELETE /*+ BKA() */ FROM t', (new Semantics(Dialect::MySql))->analyze('DELETE /*+ BKA() */ FROM t')->toString());
    }
    public function testCommonTableCannotSupplyAnOrdinaryDeletionTarget(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        $name = new Name('c');
        $with = new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [new \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression($name, [], $query)]);
        $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new QualifiedName($name));
        $this->expectExceptionMessage('An ordinary DELETE cannot target its own common-table declaration.');
        new Delete($with, [], $target);
    }

    public function testValueRejectsAnIntegerUnderParenthesesAndUnaryPlus(): void
    {
        $operand = new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus, new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
        $this->expectExceptionMessage('DELETE ordering cannot refer to an output position.');
        new Delete(null, [], new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new QualifiedName(new Name('t'))), orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem($operand)]);
    }

}
