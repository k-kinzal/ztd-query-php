<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\DropIndexStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropIndexStatement::class)]
#[Medium]
final class DropIndexStatementTest extends TestCase
{
    public function testBindsSeveralPostgreSqlIndexesWithBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE INDEX ix ON t(id)')))->bind('DROP INDEX IF EXISTS ix, iy CASCADE');
        self::assertInstanceOf(DropIndexStatement::class, $statement);
        self::assertSame([['ix'], ['iy']], array_map(static fn ($name): array => $name->parts, $statement->names));
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame(StatementKind::Drop, $statement->kind);
        self::assertSame('DROP INDEX IF EXISTS "ix", "iy" CASCADE', $statement->toString());
    }

    public function testWithOriginPreservesTheNamesAndPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)', 'CREATE INDEX ix ON t(id)')))->bind('DROP INDEX IF EXISTS ix');
        self::assertInstanceOf(DropIndexStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->names, $copy->names);
        self::assertTrue($copy->ifExists);
        self::assertSame('DROP INDEX IF EXISTS "ix"', $copy->toString());
    }

    public function testRejectsSeveralSqliteIndexesBeforeSerialization(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX ix, iy');
        self::assertInstanceOf(DropIndexStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropIndexStatement(new Origin('s0', $statement->source, Dialect::Sqlite), $statement->names);
    }

    public function testRejectsTheMySqlLanguageWhichNamesTheOwningTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX ix');
        self::assertInstanceOf(DropIndexStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropIndexStatement(new Origin('s0', $statement->source, Dialect::MySql), $statement->names);
    }

    public function testRejectsAnEmptyTargetList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP INDEX ix');
        self::assertInstanceOf(DropIndexStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new DropIndexStatement($statement->origin, []);
    }

    public function testIfExistsDefaultsToOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE INDEX ix ON t(id)')))->bind('DROP INDEX ix');
        self::assertInstanceOf(DropIndexStatement::class, $statement);
        self::assertFalse((new DropIndexStatement($statement->origin, $statement->names))->ifExists);
    }
}
