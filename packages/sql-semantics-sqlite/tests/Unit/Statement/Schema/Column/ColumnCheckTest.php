<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ColumnCheck::class)]
#[Medium]
final class ColumnCheckTest extends TestCase
{
    public function testDeriveConstraintResolvesColumnsOfTheTableBeingDefined(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT CHECK (a < b), b INT)', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ColumnCheck::class, $check);
        self::assertInstanceOf(Binary::class, $check->expression);
        $right = $operation->facts->scalar($check->expression->right)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $right);
        self::assertSame($statement, $right->relation);
        self::assertSame($operation->declarations()[0]->columns[1], $right->slot->column);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintSeesTheRowIdentifier(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a CHECK (rowid > 0))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->columns[1]->constraints[0];
        self::assertInstanceOf(ColumnCheck::class, $check);
        self::assertInstanceOf(Binary::class, $check->expression);
        $left = $operation->facts->scalar($check->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $left);
        self::assertSame($operation->declarations()[0]->columns[0], $left->slot->column);
    }

    public function testDeriveConstraintReportsANameThatIsNoColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a CHECK (missing > 0))', []);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheConditionInParentheses(): void
    {
        self::assertSame('CREATE TABLE t (a CHECK (a > 0))', (new Semantics(Dialect::Sqlite))->analyze('create table t (a check(a>0))')->toString());
    }
}
