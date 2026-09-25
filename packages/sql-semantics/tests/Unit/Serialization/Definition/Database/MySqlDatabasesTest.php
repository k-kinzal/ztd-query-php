<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Database\MySqlDatabases::class)]
#[Medium]
final class MySqlDatabasesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'CREATE DATABASE app CHARACTER SET DEFAULT'])]
    #[TestWith(['mysql-5.7.44', 'ALTER DATABASE old UPGRADE DATA DIRECTORY NAME'])]
    #[TestWith(['mysql-8.0.44', "CREATE DATABASE app ENCRYPTION 'y'"])]
    #[TestWith(['mysql-8.4.7', 'ALTER DATABASE READ ONLY DEFAULT'])]
    #[TestWith(['mysql-9.1.0', 'ALTER DATABASE app READ ONLY 1 COLLATE utf8mb4_bin'])]
    public function testWritePreservesTheOperationAcrossRebinding(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $first = $binder->bind($sql);
        $second = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($first));
        self::assertSame($first::class, $second::class);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($first), (new \SqlSemantics\SimpleSerializer())->serialize($second));
    }

    public function testWriteReturnsNullForAnUnrelatedOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Database\MySqlDatabases::write($statement));
    }

    public function testOptionQuotesIdentifierBoundariesRatherThanAcceptingSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $changed = $statement->withOptions([new DatabaseCharacterSet('a`b')]);
        self::assertSame('CREATE DATABASE `app` CHARACTER SET `a``b`', $changed->toString());
    }
}
