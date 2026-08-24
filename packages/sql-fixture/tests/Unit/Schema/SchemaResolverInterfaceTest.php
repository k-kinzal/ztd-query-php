<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaResolverInterface;

#[CoversNothing]
final class SchemaResolverInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(SchemaResolverInterface::class));
    }

    #[Test]
    public function declaresResolveAndHas(): void
    {
        $reflection = new \ReflectionClass(SchemaResolverInterface::class);

        self::assertTrue($reflection->hasMethod('resolve'));
        self::assertTrue($reflection->hasMethod('has'));
        self::assertCount(1, $reflection->getMethod('resolve')->getParameters());
    }
}
