<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql90Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql90Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql90ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql90Container();
        self::assertSame('container-registry.oracle.com/mysql/community-server:9.0.1', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-9.0.1', MySql90Container::getGrammarVersion());
    }
}
