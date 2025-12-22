<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaParserInterface;

#[CoversNothing]
final class SchemaParserInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(SchemaParserInterface::class));
    }

    #[Test]
    public function declaresParseMethod(): void
    {
        $reflection = new \ReflectionClass(SchemaParserInterface::class);
        self::assertTrue($reflection->hasMethod('parse'));

        $method = $reflection->getMethod('parse');
        self::assertCount(1, $method->getParameters());
    }
}
