<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql57Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql57Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql57ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql57Container();
        self::assertSame('mysql:5.7.44', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-5.7.44', MySql57Container::getGrammarVersion());
    }
}
