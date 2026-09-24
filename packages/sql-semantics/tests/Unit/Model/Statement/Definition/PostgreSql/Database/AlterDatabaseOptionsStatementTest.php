<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseOptionsStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDatabaseOptionsStatement::class)]
#[Medium]
final class AlterDatabaseOptionsStatementTest extends TestCase
{
    public function testWithOriginRetainsThePropertyChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app CONNECTION LIMIT 3');
        self::assertInstanceOf(AlterDatabaseOptionsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertEquals([new DatabaseOption(DatabaseParameter::ConnectionLimit, 3)], $copy->options);
        self::assertSame(StatementKind::Alter, $copy->kind);
    }

    public function testWithNameReplacesTheAlteredDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app CONNECTION LIMIT 3');
        self::assertInstanceOf(AlterDatabaseOptionsStatement::class, $statement);
        self::assertSame('ALTER DATABASE "other" WITH CONNECTION LIMIT = 3', $statement->withName('other')->toString());
        self::assertSame('app', $statement->name);
    }

    public function testWithOptionsAcceptsAnEmptyChangeAndRejectsCreationProperties(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app CONNECTION LIMIT 3');
        self::assertInstanceOf(AlterDatabaseOptionsStatement::class, $statement);
        self::assertSame('ALTER DATABASE "app"', $statement->withOptions([])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new DatabaseOption(DatabaseParameter::Tablespace, 'fast')]);
    }
}
