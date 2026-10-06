<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;

#[CoversClass(ColumnUnique::class)]
#[Medium]
final class ColumnUniqueTest extends TestCase
{
    public function testDeriveConstraintRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a UNIQUE)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheConflictResolution(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a unique on conflict rollback)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(ColumnUnique::class, $statement->columns[0]->constraints[0]);
        self::assertSame(ConflictResolution::Rollback, $statement->columns[0]->constraints[0]->conflict);
        self::assertSame('CREATE TABLE t (a UNIQUE ON CONFLICT ROLLBACK)', $operation->toString());
    }
}
