<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\UpgradeDatabaseDirectoryStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpgradeDatabaseDirectoryStatement::class)]
#[Medium]
final class UpgradeDatabaseDirectoryStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalAndQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER DATABASE app UPGRADE DATA DIRECTORY NAME');
        self::assertInstanceOf(UpgradeDatabaseDirectoryStatement::class, $statement);
        $changed = $statement->withName('new`db');
        self::assertSame('app', $statement->name);
        self::assertSame('new`db', $changed->name);
        self::assertStringContainsString('`new``db`', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithNameRejectsAnEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER DATABASE app UPGRADE DATA DIRECTORY NAME');
        self::assertInstanceOf(UpgradeDatabaseDirectoryStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER DATABASE app UPGRADE DATA DIRECTORY NAME');
        self::assertInstanceOf(UpgradeDatabaseDirectoryStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER DATABASE app UPGRADE DATA DIRECTORY NAME');
        self::assertInstanceOf(UpgradeDatabaseDirectoryStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

}
