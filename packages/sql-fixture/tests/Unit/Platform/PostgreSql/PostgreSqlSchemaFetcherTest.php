<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher;

#[CoversClass(PostgreSqlSchemaFetcher::class)]
final class PostgreSqlSchemaFetcherTest extends TestCase
{
}
