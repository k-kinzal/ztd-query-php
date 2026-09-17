<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql81Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql81Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql81ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql81Container();
        self::assertSame('mysql:8.1.0', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-8.1.0', MySql81Container::getGrammarVersion());
    }
}
