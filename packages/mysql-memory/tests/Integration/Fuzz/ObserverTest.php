<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class ObserverTest extends TestCase
{
    public function testObserveRetainsEveryResultAndTheInsertId(): void
    {
        $server = Server::start('8.4.7', ['d']);
        $pdo = new PDO($server->dsn('d'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY)');
        $observation = (new Observer())->observe($pdo, 'INSERT INTO t VALUES (7); SELECT 1; SELECT 2', true);
        $server->stop();

        self::assertSame([
            'results' => [
                ['affected' => 1, 'lastInsertId' => '7'],
                ['columns' => [['1', '', 'LONGLONG', 2, 0, ['not_null']]], 'rows' => [[1]]],
                ['columns' => [['2', '', 'LONGLONG', 2, 0, ['not_null']]], 'rows' => [[2]]],
            ],
            'warnings' => [],
        ], $observation);
    }

    public function testObserveRetainsEarlierResultsWhenALaterStatementFails(): void
    {
        $server = Server::start('8.4.7', ['d']);
        $pdo = new PDO($server->dsn('d'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $observation = (new Observer())->observe($pdo, 'SELECT 1; SELECT missing; SELECT 2', true);
        $server->stop();

        self::assertSame([['columns' => [['1', '', 'LONGLONG', 2, 0, ['not_null']]], 'rows' => [[1]]]], $observation['results']);
        self::assertSame([1054, '42S22', "Unknown column 'missing' in 'field list'"], $observation['error']);
    }
}
