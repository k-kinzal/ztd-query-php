<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\CurrentDatabase;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Statement\Definition\MySql\AlterDatabaseStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDatabaseStatement::class)]
#[Medium]
final class AlterDatabaseStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        $changed = $statement->withName('new`db');
        self::assertSame('app', $statement->name);
        self::assertSame('new`db', $changed->name);
        self::assertStringContainsString('`new``db`', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithNameRejectsAnEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithOptionsReplacesTheRequestedChangesTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        $changed = $statement->withOptions([DatabaseReadOnly::Enabled, DatabaseEncryption::Disabled]);
        self::assertInstanceOf(DatabaseCharacterSet::class, $statement->options[0]);
        self::assertSame([DatabaseReadOnly::Enabled, DatabaseEncryption::Disabled], $changed->options);
        self::assertSame('app', $changed->name);
    }

    public function testWithOptionsRejectsContradictoryAccessRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([DatabaseReadOnly::Disabled, DatabaseReadOnly::Enabled]);
    }

    public function testWithNameResolvesTheCurrentDatabaseUsingTheBindingSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, 'defaultdb'))->build()))->bind('ALTER DATABASE app CHARACTER SET utf8mb4');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        self::assertSame('defaultdb', $statement->withName(CurrentDatabase::Session)->name);
        self::assertSame('app', $statement->name);
    }
}
