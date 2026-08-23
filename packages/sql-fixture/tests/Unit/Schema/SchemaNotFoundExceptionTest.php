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
    public function namesTheMissingTable(): void
    {
        self::assertSame(
            'Schema not found for table: order',
            SchemaNotFoundException::forTable('order')->getMessage()
        );
    }

    #[Test]
    public function listsKnownTablesAlphabetically(): void
    {
        self::assertSame(
            'Schema not found for table: order. Known tables: customer, product',
            SchemaNotFoundException::forTable('order', ['product', 'customer'])->getMessage()
        );
    }

    #[Test]
    public function isRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, SchemaNotFoundException::forTable('order'));
    }
}
