<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use Tests\Container\MySqlContainer;
use ZtdQuery\Adapter\Pdo\MySql\ZtdPdo;

#[CoversClass(ZtdPdo::class)]
#[Large]
final class MySqlDriverDetectionTest extends TestCase
{
    public function testSelectsMySqlAndKeepsTheExistingConnection(): void
    {
        $container = Testcontainers::run(MySqlContainer::class);
        /** @var PDO $native */
        $native = $container->getData(PDO::class);
        $database = 'ztd_' . bin2hex(random_bytes(8));
        $native->exec('CREATE DATABASE ' . $database);
        $native->exec('USE ' . $database);
        try {
            $pdo = ZtdPdo::fromPdo($native);
            self::assertSame('mysql', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            self::assertTrue($pdo->isZtdEnabled());
            $nativeId = $native->query('SELECT CONNECTION_ID()');
            $wrappedId = $pdo->query('SELECT CONNECTION_ID()');
            self::assertNotFalse($nativeId);
            self::assertNotFalse($wrappedId);
            self::assertSame($nativeId->fetchColumn(), $wrappedId->fetchColumn());
            $host = str_replace('localhost', '127.0.0.1', $container->getHost());
            $port = $container->getMappedPort(3306);
            $dsn = "mysql:host={$host};port={$port};dbname={$database}";
            $connected = ZtdPdo::connect($dsn, 'root', 'root');
            $constructed = new ZtdPdo($dsn, 'root', 'root');
            self::assertSame(ZtdPdo::class, $connected::class);
            self::assertSame(ZtdPdo::class, $constructed::class);
            self::assertTrue($connected->isZtdEnabled());
            self::assertTrue($constructed->isZtdEnabled());
        } finally {
            $native->exec('DROP DATABASE ' . $database);
        }
    }
}
