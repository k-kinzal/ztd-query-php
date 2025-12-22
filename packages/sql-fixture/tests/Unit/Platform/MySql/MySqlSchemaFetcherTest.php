<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher;
use SqlFixture\Schema\SchemaFetcherInterface;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MySqlSchemaFetcher::class)]
final class MySqlSchemaFetcherTest extends TestCase
{
    #[Test]
    public function implementsSchemaFetcherInterface(): void
    {
        $fetcher = new MySqlSchemaFetcher();
        self::assertInstanceOf(SchemaFetcherInterface::class, $fetcher);
    }
}
