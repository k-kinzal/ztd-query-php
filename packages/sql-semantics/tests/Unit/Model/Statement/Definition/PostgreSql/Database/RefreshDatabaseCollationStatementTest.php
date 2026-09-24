<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\RefreshDatabaseCollationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RefreshDatabaseCollationStatement::class)]
#[Medium]
final class RefreshDatabaseCollationStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRefreshedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app REFRESH COLLATION VERSION');
        self::assertInstanceOf(RefreshDatabaseCollationStatement::class, $statement);
        self::assertSame('app', $statement->withOrigin($statement->origin)->name);
    }

    public function testWithNameReplacesTheRefreshedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app REFRESH COLLATION VERSION');
        self::assertInstanceOf(RefreshDatabaseCollationStatement::class, $statement);
        self::assertSame('ALTER DATABASE "Other" REFRESH COLLATION VERSION', $statement->withName('Other')->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }
}
