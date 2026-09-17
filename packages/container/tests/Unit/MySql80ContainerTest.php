<?php

declare(strict_types=1);

namespace Tests\Unit;

use Container\MySql80Container;
use Container\MySqlConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySql80Container::class)]
#[UsesClass(MySqlConfiguration::class)]
final class MySql80ContainerTest extends TestCase
{
    /**
     * @throws \Testcontainers\Exceptions\InvalidFormatException
     */
    public function testImagePinsTheReusableServer(): void
    {
        $container = new MySql80Container();
        self::assertSame('mysql:8.0.44', $container->image());
        self::assertTrue($container->reuseMode()->isReuse());
    }

    public function testGetGrammarVersionMatchesTheServer(): void
    {
        self::assertSame('mysql-8.0.44', MySql80Container::getGrammarVersion());
    }
}
