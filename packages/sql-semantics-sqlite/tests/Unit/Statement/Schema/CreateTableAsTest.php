<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\QualifiedTemporaryName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CreateTableAs::class)]
#[Medium]
final class CreateTableAsTest extends TestCase
{
    public function testDeriveStatementDeclaresTheColumnsOfTheQueryOutput(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $source = $semantics->analyze('CREATE TABLE s (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('CREATE TABLE t AS SELECT b, a AS n FROM s', [$source]);
        $table = $operation->declarations()[0];

        self::assertTrue($table->complete);
        self::assertSame(['b', 'n'], array_map(static fn (object $column): string => $column->name->value, $table->columns));
        self::assertSame(['TEXT', 'INT'], array_map(static fn (object $column): string => $column->type->name(), $table->columns));
        self::assertSame(Nullability::Nullable, $table->columns[1]->nullability);
        self::assertNull($operation->shape());
    }

    public function testDeriveStatementDeclaresARowIdentifierAndNewColumnObjects(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $source = $semantics->analyze('CREATE TABLE s (a INTEGER)');
        $table = $semantics->analyze('CREATE TABLE t AS SELECT a FROM s', [$source])->declarations()[0];

        self::assertCount(1, $table->implicit);
        self::assertNotSame($source->declarations()[0]->columns[0], $table->columns[0]);
    }

    public function testDeriveStatementMarksTheDeclarationIncompleteWhenTheQueryShapeIsOpen(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t AS SELECT 1 AS one, * FROM undeclared');
        $table = $operation->declarations()[0];

        self::assertFalse($table->complete);
        self::assertSame(['one'], array_map(static fn (object $column): string => $column->name->value, $table->columns));
    }

    public function testDeriveStatementDeclaresATemporaryTableInTheTempSchema(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('CREATE TEMP TABLE main.t AS SELECT 1 AS one');

        self::assertSame('temp', $operation->declarations()[0]->name->schema?->value);
        self::assertInstanceOf(QualifiedTemporaryName::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheQueryAfterAs(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create temp table if not exists t as select 1 as one');

        self::assertInstanceOf(CreateTableAs::class, $operation->statement);
        self::assertSame('CREATE TEMP TABLE IF NOT EXISTS t AS SELECT 1 AS one', $operation->toString());
    }
}
