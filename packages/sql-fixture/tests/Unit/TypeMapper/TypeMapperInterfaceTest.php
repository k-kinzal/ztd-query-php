<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\TypeMapper\TypeMapperInterface;

#[CoversNothing]
final class TypeMapperInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(TypeMapperInterface::class));
    }

    #[Test]
    public function declaresGenerateMethod(): void
    {
        $reflection = new \ReflectionClass(TypeMapperInterface::class);
        self::assertTrue($reflection->hasMethod('generate'));

        $method = $reflection->getMethod('generate');
        self::assertCount(2, $method->getParameters());
    }
}
