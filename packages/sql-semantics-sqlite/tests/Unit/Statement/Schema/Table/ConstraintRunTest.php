<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ConstraintRun;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;

#[CoversClass(ConstraintRun::class)]
#[Medium]
final class ConstraintRunTest extends TestCase
{
    public function testRenderWritesTheConstraintsOfARunWithoutCommas(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a, constraint c check (a > 0) unique (a), check (a < 9))');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertCount(2, $statement->constraints);
        self::assertCount(3, $statement->constraints[0]->items);
        self::assertInstanceOf(ConstraintName::class, $statement->constraints[0]->items[0]);
        self::assertInstanceOf(TableCheck::class, $statement->constraints[0]->items[1]);
        self::assertInstanceOf(TableUnique::class, $statement->constraints[0]->items[2]);
        self::assertCount(1, $statement->constraints[1]->items);
        self::assertSame('CREATE TABLE t (a, CONSTRAINT c CHECK (a > 0) UNIQUE (a), CHECK (a < 9))', $operation->toString());
    }

    public function testRenderKeepsACommaThatCutsAConstraintNameOff(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, CONSTRAINT c, CHECK (a > 0))');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertCount(2, $statement->constraints);
        self::assertInstanceOf(ConstraintName::class, $statement->constraints[0]->items[0]);
        self::assertSame('CREATE TABLE t (a, CONSTRAINT c, CHECK (a > 0))', $operation->toString());
    }
}
