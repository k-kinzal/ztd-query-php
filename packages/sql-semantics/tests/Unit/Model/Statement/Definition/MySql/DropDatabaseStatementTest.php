<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\DropDatabaseStatement::class)]
#[Medium]
final class DropDatabaseStatementTest extends TestCase
{
    public function testWithNameKeepsTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE target');
        self::assertInstanceOf(Statement\DropDatabaseStatement::class, $statement);
        $changed = $statement->withName('a`b');
        self::assertSame('target', $statement->name);
        self::assertSame('DROP DATABASE `a``b`', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithIfExistsChangesOnlyTheRemovalPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE target');
        self::assertInstanceOf(Statement\DropDatabaseStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertTrue($changed->ifExists);
        self::assertSame('DROP DATABASE IF EXISTS `target`', $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE target');
        self::assertInstanceOf(Statement\DropDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->ifExists, $copy->ifExists);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE target');
        self::assertInstanceOf(Statement\DropDatabaseStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameRejectsAnEmptyDatabaseIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE app');
        self::assertInstanceOf(Statement\DropDatabaseStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }
}
