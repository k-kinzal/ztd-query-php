<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql82Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql82Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql82ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql82Container();
        self::assertSame('mysql:8.2.0', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-8.2.0', MySql82Container::getGrammarVersion());
    }
}
