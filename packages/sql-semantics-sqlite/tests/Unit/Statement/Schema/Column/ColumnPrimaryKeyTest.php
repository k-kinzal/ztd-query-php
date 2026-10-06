<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;

#[CoversClass(ColumnPrimaryKey::class)]
#[Medium]
final class ColumnPrimaryKeyTest extends TestCase
{
    public function testDeriveConstraintRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a PRIMARY KEY)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheSortOrderTheConflictResolutionAndAutoincrement(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (id integer primary key asc on conflict abort autoincrement)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ColumnPrimaryKey::class, $key);
        self::assertSame(SortDirection::Ascending, $key->direction);
        self::assertSame(ConflictResolution::Abort, $key->conflict);
        self::assertTrue($key->autoincrement);
        self::assertSame('CREATE TABLE t (id integer PRIMARY KEY ASC ON CONFLICT ABORT AUTOINCREMENT)', $operation->toString());
    }

    public function testRenderWritesABareKey(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id PRIMARY KEY)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $key = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ColumnPrimaryKey::class, $key);
        self::assertNull($key->direction);
        self::assertFalse($key->autoincrement);
        self::assertSame('CREATE TABLE t (id PRIMARY KEY)', $operation->toString());
    }
}
