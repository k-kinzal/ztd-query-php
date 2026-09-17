<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql84Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql84Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql84ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql84Container();
        self::assertSame('container-registry.oracle.com/mysql/community-server:8.4.7', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-8.4.7', MySql84Container::getGrammarVersion());
    }
}
