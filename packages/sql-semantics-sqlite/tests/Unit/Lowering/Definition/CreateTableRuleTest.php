<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\CreateTableRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTableAs;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CreateTableRule::class)]
#[Medium]
final class CreateTableRuleTest extends TestCase
{
    public function testCommandLowersBothFormsOfATableDefinition(): void
    {
        $semantics = new Inspection();

        self::assertInstanceOf(CreateTable::class, $semantics->diagnose('CREATE TABLE t (a)')->statement);
        self::assertInstanceOf(CreateTableAs::class, $semantics->diagnose('CREATE TABLE t AS SELECT 1')->statement);
    }

    public function testCommandKeepsTheHeaderFlagsAndTheName(): void
    {
        $semantics = new Inspection();
        $plain = $semantics->diagnose('CREATE TABLE t (a)')->statement;
        $full = $semantics->diagnose('CREATE TEMP TABLE IF NOT EXISTS aux.t (a)')->statement;

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
        self::assertInstanceOf(CreateView::class, (new Inspection())->diagnose('CREATE VIEW v AS SELECT 1')->statement);
    }

    public function testColumnsKeepTheirWrittenOrder(): void
    {
        $statement = (new Inspection())->diagnose('CREATE TABLE t (c, a, b)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame(['c', 'a', 'b'], array_map(static fn (object $column): string => $column->name->value, $statement->columns));
    }
}
