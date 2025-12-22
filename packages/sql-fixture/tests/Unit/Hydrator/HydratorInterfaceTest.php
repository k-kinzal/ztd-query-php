<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\HydratorInterface;

#[CoversNothing]
final class HydratorInterfaceTest extends TestCase
{
    #[Test]
    public function interfaceExists(): void
    {
        self::assertTrue(interface_exists(HydratorInterface::class));
    }

    #[Test]
    public function declaresHydrateMethod(): void
    {
        $reflection = new \ReflectionClass(HydratorInterface::class);
        self::assertTrue($reflection->hasMethod('hydrate'));

        $method = $reflection->getMethod('hydrate');
        self::assertCount(2, $method->getParameters());
    }
}
