<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\CompletionWait;
use SqlSemantics\Model\Statement\Definition\MySql\Storage\DropTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTablespaceStatement::class)]
#[Medium]
final class DropTablespaceStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $changed = $statement->withName('with`quote');
        self::assertSame('store', $statement->name);
        self::assertSame('with`quote', $changed->name);
        self::assertStringContainsString('`with``quote`', $changed->toString());
    }

    public function testWithNameRejectsAnEmptyStorageIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithEngineReplacesAndRemovesAnExplicitSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $changed = $statement->withEngine('e`x');
        self::assertNull($statement->engine);
        self::assertSame('e`x', $changed->engine);
        self::assertStringContainsString('ENGINE `e``x`', $changed->toString());
        self::assertNull($changed->withEngine(null)->engine);
    }

    public function testWithEngineRejectsAnEmptySelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withEngine('');
    }

    public function testWithOriginPreservesTheNativeRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store ENGINE NDB');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->engine, $copy->engine);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertNotSame($statement, $copy);
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithWaitingChangesTheCompletionPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP TABLESPACE store');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $changed = $statement->withWaiting(CompletionWait::NoWait);
        self::assertSame(CompletionWait::Wait, $statement->waiting);
        self::assertSame(CompletionWait::NoWait, $changed->waiting);
        self::assertStringEndsWith('NO_WAIT', $changed->toString());
    }
}
