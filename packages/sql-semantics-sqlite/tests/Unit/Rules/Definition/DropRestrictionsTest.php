<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\DropRestrictions;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DropRestrictions::class)]
#[Medium]
final class DropRestrictionsTest extends TestCase
{
    public function testAttachKeepsTheDeclaredColumnsAndNativeRowidObject(): void
    {
        $binding = (new Inspection())->diagnose('CREATE TABLE t (a INTEGER PRIMARY KEY, b INTEGER, c AS (b+1))');
        $table = $binding->declarations()[0];
        self::assertInstanceOf(CreateTable::class, $binding->statement);

        $declaration = (new DropRestrictions())->attach($binding->statement, $binding->facts, $table);

        self::assertSame($table->columns, $declaration->columns);
        self::assertSame($table->implicit, $declaration->implicit);
        self::assertSame([0 => true, 1 => true], $declaration->columnRemoval?->protected);
    }

    public function testProtectedIncludesTheChildTermOfATableForeignKey(): void
    {
        $binding = (new Inspection())->diagnose('CREATE TABLE t (parent_id INTEGER, b INTEGER, FOREIGN KEY (parent_id) REFERENCES parent(id), UNIQUE(b))');
        self::assertInstanceOf(CreateTable::class, $binding->statement);

        self::assertSame([0, 1], (new DropRestrictions())->protected($binding->statement, $binding->facts, $binding->declarations()[0]));
    }

    public function testInlineOnlyProtectsAnInputOtherThanItsOwner(): void
    {
        $binding = (new Inspection())->diagnose('CREATE TABLE t (a INTEGER, b INTEGER CHECK (a>0 AND b<9))');
        $table = $binding->declarations()[0];
        self::assertInstanceOf(CreateTable::class, $binding->statement);
        $check = $binding->statement->columns[1]->constraints[0];
        self::assertInstanceOf(ColumnCheck::class, $check);
        $slots = [spl_object_id($table->columns[0]) => 0, spl_object_id($table->columns[1]) => 1];

        self::assertSame([0], (new DropRestrictions())->inline($check, $binding->facts, $slots, 1, true));
    }

    public function testTableCheckCanRebindAShadowedRowidAlias(): void
    {
        $binding = (new Inspection())->diagnose('CREATE TABLE t (rowid INTEGER, b INTEGER, CHECK (rowid>0))');
        $table = $binding->declarations()[0];
        self::assertInstanceOf(CreateTable::class, $binding->statement);
        $check = $binding->statement->constraints[0]->items[0];
        self::assertInstanceOf(TableCheck::class, $check);
        $slots = [spl_object_id($table->columns[0]) => 0];

        self::assertSame([], (new DropRestrictions())->table($check, $binding->facts, $slots, [], true));
        self::assertSame([0], (new DropRestrictions())->table($check, $binding->facts, $slots, [], false));
    }

    public function testUsesIgnoresTheUnreachableOperandOfEmptyMembership(): void
    {
        $binding = (new Inspection())->diagnose('CREATE TABLE t (a INTEGER, b INTEGER, CHECK ((a+1) NOT IN ()))');
        $table = $binding->declarations()[0];
        self::assertInstanceOf(CreateTable::class, $binding->statement);
        $check = $binding->statement->constraints[0]->items[0];
        self::assertInstanceOf(TableCheck::class, $check);
        $slots = [spl_object_id($table->columns[0]) => 0];

        self::assertSame([], (new DropRestrictions())->uses($check->expression, $binding->facts, $slots, null, false));
    }
}
