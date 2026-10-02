<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NotNull::class)]
#[Medium]
final class NotNullTest extends TestCase
{
    public function testDeriveConstraintMakesTheDeclaredColumnNotNull(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INT NOT NULL, b INT)', []);

        self::assertSame(Nullability::NotNull, $operation->declarations()[0]->columns[0]->nullability);
        self::assertSame(Nullability::Nullable, $operation->declarations()[0]->columns[1]->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheConflictResolution(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a not null on conflict ignore, b not null)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(NotNull::class, $statement->columns[0]->constraints[0]);
        self::assertInstanceOf(NotNull::class, $statement->columns[1]->constraints[0]);
        self::assertSame(ConflictResolution::Ignore, $statement->columns[0]->constraints[0]->conflict);
        self::assertNull($statement->columns[1]->constraints[0]->conflict);
        self::assertSame('CREATE TABLE t (a NOT NULL ON CONFLICT IGNORE, b NOT NULL)', $operation->toString());
    }
}
