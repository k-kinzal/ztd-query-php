<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\CreateTableRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;

#[CoversClass(CreateTableRule::class)]
#[Medium]
final class CreateTableRuleTest extends TestCase
{
    public function testCommandLowersBothFormsOfATableDefinition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(CreateTable::class, $semantics->analyze('CREATE TABLE t (a)')->statement);
        self::assertInstanceOf(CreateTableAs::class, $semantics->analyze('CREATE TABLE t AS SELECT 1')->statement);
    }

    public function testCommandKeepsTheHeaderFlagsAndTheName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('CREATE TABLE t (a)')->statement;
        $full = $semantics->analyze('CREATE TEMP TABLE IF NOT EXISTS aux.t (a)')->statement;

        self::assertInstanceOf(CreateTable::class, $plain);
        self::assertInstanceOf(CreateTable::class, $full);
        self::assertFalse($plain->temporary);
        self::assertFalse($plain->ifNotExists);
        self::assertTrue($full->temporary);
        self::assertTrue($full->ifNotExists);
        self::assertSame('aux', $full->name->schema?->value);
        self::assertSame('t', $full->name->name->value);
    }

    public function testCreatedAcceptsTheCreateKeywordOfEveryDefinition(): void
    {
        self::assertInstanceOf(CreateView::class, (new Semantics(Dialect::Sqlite))->analyze('CREATE VIEW v AS SELECT 1')->statement);
    }

    public function testColumnsKeepTheirWrittenOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (c, a, b)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame(['c', 'a', 'b'], array_map(static fn (object $column): string => $column->name->value, $statement->columns));
    }
}
