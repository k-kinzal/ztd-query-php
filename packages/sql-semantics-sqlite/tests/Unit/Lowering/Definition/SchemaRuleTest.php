<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\SchemaRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterDropColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterRenameTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateView;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;

#[CoversClass(SchemaRule::class)]
#[Medium]
final class SchemaRuleTest extends TestCase
{
    public function testCommandLowersEachSchemaCommandToItsOwnRequest(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(CreateView::class, $semantics->analyze('CREATE VIEW v AS SELECT 1')->statement);
        self::assertInstanceOf(CreateIndex::class, $semantics->analyze('CREATE INDEX i ON t (a)')->statement);
        self::assertInstanceOf(AlterRenameTable::class, $semantics->analyze('ALTER TABLE t RENAME TO u')->statement);
        self::assertInstanceOf(AlterAddColumn::class, $semantics->analyze('ALTER TABLE t ADD a')->statement);
        self::assertInstanceOf(AlterDropColumn::class, $semantics->analyze('ALTER TABLE t DROP a')->statement);
        self::assertInstanceOf(AlterRenameColumn::class, $semantics->analyze('ALTER TABLE t RENAME a TO b')->statement);
    }

    public function testCommandLowersEachDropToItsObjectKind(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $kinds = array_map(static fn (string $sql): ?SchemaObjectKind => $semantics->analyze($sql)->statement instanceof Drop ? $semantics->analyze($sql)->statement->object : null, ['DROP TABLE a', 'DROP VIEW a', 'DROP INDEX a', 'DROP TRIGGER a']);

        self::assertSame([SchemaObjectKind::Table, SchemaObjectKind::View, SchemaObjectKind::Index, SchemaObjectKind::Trigger], $kinds);
    }

    public function testViewKeepsTheHeaderFlagsAndTheOptionalColumnList(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('CREATE VIEW v AS SELECT 1')->statement;
        $full = $semantics->analyze('CREATE TEMP VIEW IF NOT EXISTS v (a) AS SELECT 1')->statement;

        self::assertInstanceOf(CreateView::class, $plain);
        self::assertInstanceOf(CreateView::class, $full);
        self::assertNull($plain->columns);
        self::assertFalse($plain->temporary);
        self::assertCount(1, $full->columns ?? []);
        self::assertTrue($full->temporary);
        self::assertTrue($full->ifNotExists);
    }

    public function testIndexKeepsTheFlagsTheTermsAndTheCondition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('CREATE INDEX i ON t (a)')->statement;
        $full = $semantics->analyze('CREATE UNIQUE INDEX IF NOT EXISTS aux.i ON t (a, b DESC) WHERE a > 0')->statement;

        self::assertInstanceOf(CreateIndex::class, $plain);
        self::assertInstanceOf(CreateIndex::class, $full);
        self::assertFalse($plain->unique);
        self::assertNull($plain->where);
        self::assertTrue($full->unique);
        self::assertTrue($full->ifNotExists);
        self::assertSame('t', $full->table->value);
        self::assertCount(2, $full->terms);
        self::assertNotNull($full->where);
    }

    public function testIfExistsLowersTheOptionalClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('DROP TABLE t')->statement;
        $tolerant = $semantics->analyze('DROP TABLE IF EXISTS t')->statement;

        self::assertInstanceOf(Drop::class, $plain);
        self::assertInstanceOf(Drop::class, $tolerant);
        self::assertFalse($plain->ifExists);
        self::assertTrue($tolerant->ifExists);
    }

    public function testAddedLowersTheQualifiedTableName(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('ALTER TABLE aux.t ADD a')->statement;

        self::assertInstanceOf(AlterAddColumn::class, $statement);
        self::assertSame('aux', $statement->table->schema?->value);
        self::assertSame('t', $statement->table->name->value);
    }

    public function testKeywordIsOptionalBeforeTheColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $short = $semantics->analyze('ALTER TABLE t DROP a')->statement;
        $long = $semantics->analyze('ALTER TABLE t DROP COLUMN a')->statement;

        self::assertInstanceOf(AlterDropColumn::class, $short);
        self::assertInstanceOf(AlterDropColumn::class, $long);
        self::assertSame('a', $short->column->value);
        self::assertSame('a', $long->column->value);
    }
}
