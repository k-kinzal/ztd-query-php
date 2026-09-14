<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeConnection;

#[CoversNothing]
final class ConnectionInterfaceTest extends TestCase
{
    public function testQueryAnswersAStatementOverTheRowsTheConnectionHolds(): void
    {
        $connection = new FakeConnection([], [['id' => 1]]);

        $statement = $connection->query('SELECT id FROM users');

        self::assertNotFalse($statement);
        self::assertSame([['id' => 1]], $statement->fetchAll());
    }

    public function testQueryAnswersFalseWhereTheStatementCouldNotBeRun(): void
    {
        $connection = new FakeConnection();
        $connection->failOnQuery('SELECT id FROM missing');

        self::assertFalse($connection->query('SELECT id FROM missing'));
    }
}
