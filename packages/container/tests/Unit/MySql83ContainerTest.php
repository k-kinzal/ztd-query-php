<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql83Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql83Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql83ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql83Container();
        self::assertSame('mysql:8.3.0', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-8.3.0', MySql83Container::getGrammarVersion());
    }
}
