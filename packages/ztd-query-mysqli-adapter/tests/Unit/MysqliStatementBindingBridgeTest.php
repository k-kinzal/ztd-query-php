<?php

declare(strict_types=1);

namespace Tests\Unit;

use mysqli_result;
use mysqli_stmt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge;

#[CoversClass(MysqliStatementBindingBridge::class)]
#[Large]
final class MysqliStatementBindingBridgeTest extends TestCase
{
    public function testBind_paramRetainsReferencesAcrossExecutions(): void
    {
        [$database, $connection] = MySqlContainer::createTestDatabase();
        try {
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
            $connection->query('DROP DATABASE `' . $database . '`');
        }
    }

    public function testBind_resultWritesBackToCallerVariables(): void
    {
        [$database, $connection] = MySqlContainer::createTestDatabase();
        try {
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
            $connection->query('DROP DATABASE `' . $database . '`');
        }
    }
}
