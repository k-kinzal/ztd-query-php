<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\PostgreSql16Container;
use Container\PostgreSqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgreSql16Container::class)]
#[UsesClass(PostgreSqlConfiguration::class)]
final class PostgreSql16ContainerTest extends TestCase
{
    public function testImagePinsTheReusableServer(): void
    {
        $container = new PostgreSql16Container();
        self::assertSame('postgres:16', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }
}
