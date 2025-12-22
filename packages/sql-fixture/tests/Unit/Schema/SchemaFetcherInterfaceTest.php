<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaFetcherInterface;

#[CoversNothing]
final class SchemaFetcherInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(SchemaFetcherInterface::class));
    }

    #[Test]
    public function declaresFetchSchemaMethod(): void
    {
        $reflection = new \ReflectionClass(SchemaFetcherInterface::class);
        self::assertTrue($reflection->hasMethod('fetchSchema'));

        $method = $reflection->getMethod('fetchSchema');
        self::assertCount(2, $method->getParameters());
    }
}
