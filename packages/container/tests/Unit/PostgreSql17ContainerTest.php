<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\PostgreSql17Container;
use Container\PostgreSqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgreSql17Container::class)]
#[UsesClass(PostgreSqlConfiguration::class)]
final class PostgreSql17ContainerTest extends TestCase
{
    public function testImagePinsTheReusableServer(): void
    {
        $container = new PostgreSql17Container();
        self::assertSame('postgres:17.2', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }
}
