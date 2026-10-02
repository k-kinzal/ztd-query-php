<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\TableConstraintRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ForeignKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;

#[CoversClass(TableConstraintRule::class)]
#[Medium]
final class TableConstraintRuleTest extends TestCase
{
    public function testRunsAreEmptyWithoutTableConstraints(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame([], $statement->constraints);
    }

    public function testRunsSplitTheConstraintsAtTheWrittenCommas(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, UNIQUE (a) CHECK (a > 0), CONSTRAINT n CONSTRAINT m, PRIMARY KEY (a))')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame([2, 2, 1], array_map(static fn (object $run): int => count($run->items), $statement->constraints));
    }

    public function testConstraintLowersEveryKindOfTableConstraint(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, CONSTRAINT n PRIMARY KEY (a) UNIQUE (a) CHECK (a > 0) FOREIGN KEY (a) REFERENCES p)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame([ConstraintName::class, TablePrimaryKey::class, TableUnique::class, TableCheck::class, ForeignKey::class], array_map(static fn (object $item): string => $item::class, $statement->constraints[0]->items));
    }

    public function testOptionsKeepTheirWrittenOrder(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $none = $semantics->analyze('CREATE TABLE t (a)')->statement;
        $two = $semantics->analyze('CREATE TABLE t (a PRIMARY KEY) STRICT, WITHOUT ROWID')->statement;

        self::assertInstanceOf(CreateTable::class, $none);
        self::assertInstanceOf(CreateTable::class, $two);
        self::assertSame([], $none->options);
        self::assertSame([TableOptionKind::Strict, TableOptionKind::WithoutRowid], array_map(static fn (object $option): ?TableOptionKind => $option->kind(), $two->options));
    }

    public function testOptionTellsTheWithoutFormFromTheBareForm(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a) WITHOUT x, y')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertTrue($statement->options[0]->without);
        self::assertFalse($statement->options[1]->without);
    }
}
