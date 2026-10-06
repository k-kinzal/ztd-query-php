<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;

#[CoversClass(ConstraintName::class)]
#[Medium]
final class ConstraintNameTest extends TestCase
{
    public function testDeriveConstraintRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a CONSTRAINT lonely)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheNameAtItsPlaceInAColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a constraint one constraint "two" not null constraint three)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(ConstraintName::class, $statement->columns[0]->constraints[0]);
        self::assertInstanceOf(ConstraintName::class, $statement->columns[0]->constraints[1]);
        self::assertInstanceOf(ConstraintName::class, $statement->columns[0]->constraints[3]);
        self::assertSame('two', $statement->columns[0]->constraints[1]->name->value);
        self::assertSame('CREATE TABLE t (a CONSTRAINT one CONSTRAINT two NOT NULL CONSTRAINT three)', $operation->toString());
    }

    public function testRenderKeepsTheNameAtItsPlaceAmongTableConstraints(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, CONSTRAINT k UNIQUE (a))');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertCount(2, $statement->constraints[0]->items);
        self::assertInstanceOf(ConstraintName::class, $statement->constraints[0]->items[0]);
        self::assertSame('CREATE TABLE t (a, CONSTRAINT k UNIQUE (a))', $operation->toString());
    }
}
