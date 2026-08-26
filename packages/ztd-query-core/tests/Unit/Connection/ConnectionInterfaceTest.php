<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;
use ZtdQuery\Connection\ConnectionInterface;

#[CoversNothing]
final class ConnectionInterfaceTest extends TestCase
{
    public function testFakeConnectionImplementsInterface(): void
    {
        $connection = new FakeConnection([]);

        self::assertInstanceOf(ConnectionInterface::class, $connection);
    }
}
