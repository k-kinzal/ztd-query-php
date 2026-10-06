<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NullAllowed;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NullAllowed::class)]
#[Medium]
final class NullAllowedTest extends TestCase
{
    public function testDeriveConstraintLeavesTheColumnNullable(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT NULL)', []);

        self::assertSame(Nullability::Nullable, $operation->declarations()[0]->columns[0]->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintDoesNotUndoNotNull(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT NOT NULL NULL)');

        self::assertSame(Nullability::NotNull, $operation->declarations()[0]->columns[0]->nullability);
    }

    public function testRenderKeepsTheConflictResolution(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a null on conflict fail)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(NullAllowed::class, $statement->columns[0]->constraints[0]);
        self::assertSame(ConflictResolution::Fail, $statement->columns[0]->constraints[0]->conflict);
        self::assertSame('CREATE TABLE t (a NULL ON CONFLICT FAIL)', $operation->toString());
    }
}
