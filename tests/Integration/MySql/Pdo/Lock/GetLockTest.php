<?php

declare(strict_types=1);

namespace Tests\Integration\MySql\Pdo\Lock;

use Tests\Integration\MySqlIntegrationTestCase;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * GET_LOCK is a MySQL function, not a SQL statement.
 * It is used within SELECT statements and therefore processed as a normal SELECT.
 * The matrix lists it as "Unsupported" meaning the lock functionality is not meaningful
 * in ZTD context, but the SQL syntax itself is valid and processed.
 */
final class GetLockTest extends MySqlIntegrationTestCase
{
    public function testGetLockFunctionIsProcessedAsSelect(): void
    {
        $ztdPdo = $this->createZtdPdoWithConfig(new ZtdConfig(
            unsupportedBehavior: UnsupportedSqlBehavior::Exception
        ));

        // GET_LOCK is a function within SELECT, so it is processed as a normal SELECT statement
        $result = $ztdPdo->query("SELECT GET_LOCK('my_lock', 0)");

        $this->assertNotFalse($result);
    }
}
