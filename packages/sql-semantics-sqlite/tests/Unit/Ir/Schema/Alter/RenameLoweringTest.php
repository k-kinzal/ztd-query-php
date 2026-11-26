<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(RenameLowering::class)]
#[Small]
final class RenameLoweringTest extends TestCase
{
    public function testLowerUsesTheTemporaryTableSelectedByTheNativeSearchOrder(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE data (id INTEGER)', 'CREATE TEMP TABLE data (id INTEGER)']);
        $value = $semantics->analyze('ALTER TABLE DATA RENAME TO scratch', $context);

        self::assertInstanceOf(RenameTable::class, $value);
        self::assertSame('temp', $value->schema->value);
        self::assertSame('data', $value->table->value);
    }

    public function testColumnUsesTheDeclaredSpellingOfAnExplicitRowidName(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE data ("RowId" INTEGER PRIMARY KEY)']);
        $value = $semantics->analyze('ALTER TABLE data RENAME COLUMN ROWID TO stored', $context);

        self::assertInstanceOf(RenameColumn::class, $value);
        self::assertSame('RowId', $value->column->value);
        self::assertSame('stored', $value->destination->value);
    }
}
