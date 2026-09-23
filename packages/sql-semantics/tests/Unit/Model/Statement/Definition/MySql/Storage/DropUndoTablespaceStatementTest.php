<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropUndoTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropUndoTablespaceStatement::class)]
#[Medium]
final class DropUndoTablespaceStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $changed = $statement->withName('with`quote');
        self::assertSame('store', $statement->name);
        self::assertSame('with`quote', $changed->name);
        self::assertStringContainsString('`with``quote`', $changed->toString());
    }

    public function testWithNameRejectsAnEmptyStorageIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithEngineReplacesAndRemovesAnExplicitSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $changed = $statement->withEngine('e`x');
        self::assertNull($statement->engine);
        self::assertSame('e`x', $changed->engine);
        self::assertStringContainsString('ENGINE `e``x`', $changed->toString());
        self::assertNull($changed->withEngine(null)->engine);
    }

    public function testWithEngineRejectsAnEmptySelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withEngine('');
    }

    public function testWithOriginPreservesTheNativeRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store ENGINE NDB');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->engine, $copy->engine);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertNotSame($statement, $copy);
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithOriginRejectsALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP UNDO TABLESPACE store');
        self::assertInstanceOf(DropUndoTablespaceStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
