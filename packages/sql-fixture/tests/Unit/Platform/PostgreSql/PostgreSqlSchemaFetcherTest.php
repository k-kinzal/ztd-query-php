<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher;
use SqlFixture\Schema\SchemaFetcherInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PostgreSqlSchemaFetcher::class)]
final class PostgreSqlSchemaFetcherTest extends TestCase
{
    #[Test]
    public function implementsSchemaFetcherInterface(): void
    {
        $fetcher = new PostgreSqlSchemaFetcher();
        self::assertInstanceOf(SchemaFetcherInterface::class, $fetcher);
    }
}
