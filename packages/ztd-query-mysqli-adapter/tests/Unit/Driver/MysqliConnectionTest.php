<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\FakeConnectionProperties;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliResult;
use ZtdQuery\Adapter\Mysqli\Driver\ConnectionState;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;

#[CoversClass(MysqliConnection::class)]
#[UsesClass(ConnectionState::class)]
#[UsesClass(MysqliResultStatement::class)]
#[UsesClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliProperties::class)]
final class MysqliConnectionTest extends TestCase
{
    public function testItIsTheConnectionZtdReadsAndWritesThrough(): void
    {
        self::assertContains(ConnectionInterface::class, class_implements(new MysqliConnection(new StubMysqli())));
    }

    public function testQueryAnswersAStatementOverTheRowsTheServerSentBack(): void
    {
        $mysqli = new StubMysqli();
        $mysqli->queryReturn = StubMysqliResult::create([['id' => 1], ['id' => 2]]);
        $connection = new MysqliConnection($mysqli, new FakeConnectionProperties(['affected_rows' => 2]));

        $statement = $connection->query('SELECT id FROM users');

        self::assertSame([['id' => 1], ['id' => 2]], $statement === false ? [] : $statement->fetchAll());
    }

    public function testQueryAnswersHowManyRowsAStatementWithNoResultAffected(): void
    {
        $mysqli = new StubMysqli();
        $mysqli->queryReturn = true;
        $connection = new MysqliConnection($mysqli, new FakeConnectionProperties(['affected_rows' => 3]));

        $statement = $connection->query('DELETE FROM users');

        self::assertSame(3, $statement === false ? -1 : $statement->rowCount());
    }

    public function testQueryRaisesWhatTheDriverSaidAboutAStatementItRefused(): void
    {
        $mysqli = new StubMysqli();
        $mysqli->queryReturn = false;
        $connection = new MysqliConnection($mysqli, new FakeConnectionProperties([
            'errno' => 1146,
            'error' => "Table 'users' doesn't exist",
        ]));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage("Table 'users' doesn't exist");

        $connection->query('SELECT id FROM users');
    }

    public function testQueryAnswersFalseWhereTheStatementDidNotRunAndTheDriverSaidNothing(): void
    {
        $mysqli = new StubMysqli();
        $mysqli->queryReturn = false;
        $connection = new MysqliConnection($mysqli, new FakeConnectionProperties());

        self::assertFalse($connection->query('SELECT id FROM users'));
    }
}
