<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaNotFoundException;

#[CoversClass(SchemaNotFoundException::class)]
final class SchemaNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function testForTableNamesTheMissingTable(): void
    {
        self::assertSame(
            'Schema not found for table: order',
            (new SchemaNotFoundException('order'))->getMessage()
        );
    }

    #[Test]
    public function testListsKnownTablesAlphabetically(): void
    {
        self::assertSame(
            'Schema not found for table: order. Known tables: customer, product',
            (new SchemaNotFoundException('order', ['product', 'customer']))->getMessage()
        );
    }

    public function testRetainsTheMissingTableAndAvailableSchemas(): void
    {
        $exception = new SchemaNotFoundException('orders', ['products', 'customers']);
        self::assertSame('orders', $exception->tableName);
        self::assertSame(['customers', 'products'], $exception->knownTables);
    }
}
