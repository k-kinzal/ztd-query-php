<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateDatabaseStatement::class)]
#[Medium]
final class CreateDatabaseStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $changed = $statement->withName('new`db');
        self::assertSame('app', $statement->name);
        self::assertSame('new`db', $changed->name);
        self::assertStringContainsString('`new``db`', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithNameRejectsAnEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithOptionsReplacesDefaultsWithoutChangingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $changed = $statement->withOptions([new DatabaseCharacterSet('utf8mb4'), new DatabaseCollation('utf8mb4_bin'), DatabaseEncryption::Enabled]);
        self::assertSame([], $statement->options);
        self::assertInstanceOf(DatabaseCharacterSet::class, $changed->options[0]);
        self::assertInstanceOf(DatabaseCollation::class, $changed->options[1]);
        self::assertSame('utf8mb4', $changed->options[0]->name);
        self::assertSame('utf8mb4_bin', $changed->options[1]->name);
        self::assertSame(DatabaseEncryption::Enabled, $changed->options[2]);
        self::assertSame([], $changed->withOptions([])->options);
    }

    public function testWithIfNotExistsChangesOnlyTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertTrue($changed->ifNotExists);
        self::assertSame($statement->name, $changed->name);
    }
}
