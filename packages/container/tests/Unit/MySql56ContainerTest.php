<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql56Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql56Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql56ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql56Container();
        self::assertSame('mysql:5.6.51', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-5.6.51', MySql56Container::getGrammarVersion());
    }
}
