<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlCatalog;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser;
use SqlFixture\Platform\PostgreSql\PostgreSqlTableDeclaration;

#[CoversClass(PostgreSqlSchemaFetcher::class)]
#[UsesClass(PostgreSqlCatalog::class)]
#[UsesClass(PostgreSqlSchemaParser::class)]
#[UsesClass(PostgreSqlTableDeclaration::class)]
final class PostgreSqlSchemaFetcherTest extends TestCase
{
    public function testFetchSchemaNeedsAConnectionThatHasAnInformationSchema(): void
    {
        $this->expectException(PDOException::class);

        (new PostgreSqlSchemaFetcher())->fetchSchema(new PDO('sqlite::memory:'), 'users');
    }
}
