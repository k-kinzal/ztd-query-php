<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateDatabaseStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateDatabaseStatement::class)]
#[Medium]
final class ShowCreateDatabaseStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE SCHEMA app');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        self::assertSame('app', $statement->database);
        self::assertFalse($statement->ifNotExists);
        self::assertSame(['Database', 'Create Database'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW CREATE DATABASE `app`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithDatabaseDescribesAnotherDatabaseImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE DATABASE app');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        $changed = $statement->withDatabase('other');
        self::assertNotSame($statement, $changed);
        self::assertSame('app', $statement->database);
        self::assertSame('other', $changed->database);
        self::assertSame('SHOW CREATE DATABASE `other`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfNotExistsTogglesTheReturnedDefinitionImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE DATABASE IF NOT EXISTS app');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        $changed = $statement->withIfNotExists(false);
        self::assertNotSame($statement, $changed);
        self::assertTrue($statement->ifNotExists);
        self::assertSame('SHOW CREATE DATABASE `app`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW CREATE DATABASE IF NOT EXISTS `app`', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withIfNotExists(true)));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE DATABASE IF NOT EXISTS app');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->database, $statement->ifNotExists], [$copy->database, $copy->ifNotExists]);
    }

    public function testRejectsAnEmptyDatabaseName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE DATABASE app');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowCreateDatabaseStatement($statement->origin, '');
    }
}
