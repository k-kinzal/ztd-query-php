<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeyLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(TableKeyLowering::class)]
#[Small]
final class TableKeyLoweringTest extends TestCase
{
    public function testInlineKeySelectsItsDeclaredColumn(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE logs (message TEXT, id INTEGER PRIMARY KEY DESC ON CONFLICT IGNORE)');
        self::assertInstanceOf(CreateTable::class, $operation->statement);

        $columns = [new TableColumn(new Identifier('message')), new TableColumn(new Identifier('id'))];
        $keys = (new TableKeyLowering())->inline($operation->statement, $columns);
        $key = $keys[0];

        self::assertInstanceOf(PrimaryKey::class, $key);
        self::assertTrue($key->inline);
        self::assertSame(1, $key->terms[0]->slot);
        self::assertTrue($key->terms[0]->descending);
        self::assertSame(KeyConflict::Ignore, $key->conflict);
    }

    public function testLowerKeepsTheTableConstraintOrder(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE logs (message TEXT, id INTEGER, PRIMARY KEY (id DESC, message))');
        self::assertInstanceOf(CreateTable::class, $operation->statement);

        $columns = [new TableColumn(new Identifier('message')), new TableColumn(new Identifier('id'))];
        $keys = (new TableKeyLowering())->lower($operation->statement, $columns);
        $key = $keys[0];

        self::assertInstanceOf(PrimaryKey::class, $key);
        self::assertFalse($key->inline);
        self::assertSame([1, 0], array_column($key->terms, 'slot'));
    }

    public function testLowerKeepsInlineUniqueBeforeTablePrimary(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE logs (message TEXT UNIQUE, PRIMARY KEY (message))');
        self::assertInstanceOf(CreateTable::class, $operation->statement);
        $keys = (new TableKeyLowering())->lower($operation->statement, [new TableColumn(new Identifier('message'))]);

        self::assertInstanceOf(UniqueKey::class, $keys[0]);
        self::assertTrue($keys[0]->inline);
        self::assertInstanceOf(PrimaryKey::class, $keys[1]);
        self::assertFalse($keys[1]->inline);
    }
}
