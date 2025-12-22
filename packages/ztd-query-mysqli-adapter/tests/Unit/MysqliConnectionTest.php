<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqli;
use ZtdQuery\Adapter\Mysqli\MysqliConnection;
use ZtdQuery\Connection\ConnectionInterface;

#[CoversClass(MysqliConnection::class)]
final class MysqliConnectionTest extends TestCase
{
    public function testImplementsConnectionInterface(): void
    {
        $mysqli = new StubMysqli();
        $connection = new MysqliConnection($mysqli);

        self::assertInstanceOf(ConnectionInterface::class, $connection);
    }
}
