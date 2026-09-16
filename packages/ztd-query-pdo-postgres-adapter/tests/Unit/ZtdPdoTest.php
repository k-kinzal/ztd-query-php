<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\Postgres\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\SessionFactory;

#[CoversClass(ZtdPdo::class)]
#[\PHPUnit\Framework\Attributes\Large]
final class ZtdPdoTest extends TestCase
{
    public function testRejectsAnotherDriverBeforeCreatingASession(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $factory = $this->createMock(SessionFactory::class);
        $factory->expects(self::never())->method('create');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The PostgreSql PDO adapter requires the "pgsql" driver.');
        ZtdPdo::fromPdo($pdo, factory: $factory);
    }

    public function testPreservesAnExplicitFactoryAndConfiguration(): void
    {
        $container = \Testcontainers\Testcontainers::run(\Tests\Container\PostgreSqlContainer::class);
        $pdo = $container->getData(PDO::class);
        $config = new ZtdConfig();
        $factory = $this->createMock(SessionFactory::class);
        $factory->expects(self::once())->method('create')
            ->with(self::isInstanceOf(ConnectionInterface::class), self::identicalTo($config))
            ->willThrowException(new RuntimeException('Custom factory selected.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Custom factory selected.');
        ZtdPdo::fromPdo($pdo, $config, $factory);
    }
}
