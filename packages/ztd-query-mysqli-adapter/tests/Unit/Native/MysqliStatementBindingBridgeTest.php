<?php

declare(strict_types=1);

namespace Tests\Unit\Native;

use Container\MySql80Container;
use Container\MySql84Container;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\Native\MysqliStatementBindingBridge;

#[CoversClass(MysqliStatementBindingBridge::class)]
#[Large]
final class MysqliStatementBindingBridgeTest extends TestCase
{
    public function testBind_paramRetainsReferencesAcrossExecutions(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = new mysqli(str_replace('localhost', '127.0.0.1', $container->getHost()), 'root', 'root', 'test', $container->getMappedPort(3306));
            $connection->set_charset('utf8mb4');
            $statement = $connection->prepare('SELECT ? AS value');
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $bridge = new class ($statement) extends MysqliStatementBindingBridge {};
            $value = 7;
            self::assertTrue($bridge->bind_param('i', $value));
            $value = 42;
            self::assertTrue($statement->execute());
            $result = $statement->get_result();
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['value' => 42]], $result->fetch_all(MYSQLI_ASSOC));
            $statement->close();
        } finally {
            $container->stop();
        }
    }

    public function testBind_resultWritesBackToCallerVariables(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $connection = new mysqli(str_replace('localhost', '127.0.0.1', $container->getHost()), 'root', 'root', 'test', $container->getMappedPort(3306));
            $connection->set_charset('utf8mb4');
            $statement = $connection->prepare("SELECT 7 AS id, 'Alice' AS name");
            self::assertInstanceOf(mysqli_stmt::class, $statement);
            $bridge = new class ($statement) extends MysqliStatementBindingBridge {};
            $id = null;
            $name = null;
            self::assertTrue($statement->execute());
            self::assertTrue($bridge->bind_result($id, $name));
            self::assertTrue($statement->fetch());
            self::assertSame(7, $id);
            self::assertSame('Alice', $name);
            self::assertNull($statement->fetch());
            $statement->close();
        } finally {
            $container->stop();
        }
    }
}
