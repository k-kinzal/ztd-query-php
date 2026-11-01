<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Instance;
use MySqlMemory\Registry\ForeignServer;
use MySqlMemory\Registry\Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForeignServer::class)]
#[Small]
final class ForeignServerTest extends TestCase
{
    public function testOptionsAreKeptWithTheNameAndWrapper(): void
    {
        $server = new ForeignServer('s', 'mysql', ['host' => 'h', 'port' => 3306]);

        self::assertSame(['s', 'mysql', ['host' => 'h', 'port' => 3306]], [$server->name, $server->wrapper, $server->options]);
    }

    public function testOptionsHoldTheValuesCreateServerGives(): void
    {
        $instance = new Instance();
        $instance->connect()->query("CREATE SERVER s1 FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h', PORT 3306, USER 'u')");
        $server = $instance->registry->servers[Registry::key('s1')] ?? null;

        self::assertNotNull($server);
        self::assertSame(['host' => 'h', 'port' => 3306, 'user' => 'u'], $server->options);
    }
}
