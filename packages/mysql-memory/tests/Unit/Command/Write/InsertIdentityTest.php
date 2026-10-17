<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Write;

use MySqlMemory\Command\Write\InsertIdentity;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertIdentity::class)]
#[Small]
final class InsertIdentityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function providerInsertIds(): iterable
    {
        yield 'explicit identity' => ['INSERT INTO t VALUES (7,7)', 7, '99'];
        yield 'last explicit identity' => ['INSERT INTO t VALUES (7,7), (8,8)', 8, '99'];
        yield 'first generated identity' => ['INSERT INTO t VALUES (NULL,7), (8,8)', 3, '3'];
        yield 'changed duplicate' => ['INSERT INTO t VALUES (1,1) ON DUPLICATE KEY UPDATE a=3', 1, '99'];
        yield 'unchanged duplicate' => ['INSERT INTO t VALUES (1,1) ON DUPLICATE KEY UPDATE a=a', 0, '99'];
        yield 'unused generated identity' => ['INSERT INTO t VALUES (NULL,1) ON DUPLICATE KEY UPDATE a=3', 1, '99'];
        yield 'ignored identity' => ['INSERT IGNORE INTO t VALUES (NULL,1)', 0, '99'];
        yield 'last duplicate after insertion' => ['INSERT INTO t VALUES (7,7), (1,1) ON DUPLICATE KEY UPDATE a=a', 1, '99'];
        yield 'generated precedes function' => ['INSERT INTO t (a) VALUES (LAST_INSERT_ID(77))', 3, '3'];
        yield 'function precedes explicit' => ['INSERT INTO t VALUES (7,LAST_INSERT_ID(77))', 77, '77'];
        yield 'function on unchanged duplicate' => ['INSERT INTO t VALUES (1,1) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)', 1, '1'];
        yield 'replacement' => ['REPLACE INTO t VALUES (1,1)', 1, '99'];
    }

    #[DataProvider('providerInsertIds')]
    public function testFinishKeepsTheProtocolIdSeparateFromTheSessionId(string $sql, int $packet, string $sessionId): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, a INT UNIQUE); INSERT INTO t VALUES (1,1), (2,2); SELECT LAST_INSERT_ID(99)');
        $result = $session->query($sql)[0];
        $last = $session->query('SELECT LAST_INSERT_ID()')[0];

        self::assertInstanceOf(Completion::class, $result);
        self::assertSame($packet, $result->lastInsertId);
        self::assertInstanceOf(ResultSet::class, $last);
        self::assertSame([[$sessionId]], $last->rows);
    }

    public function testInsertedReportsZeroWithoutAnAutoIncrementColumn(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT PRIMARY KEY)');
        $reply = $session->query('INSERT INTO t VALUES (7)')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->lastInsertId);
    }

    public function testUpdatedReportsZeroForAnUnchangedDuplicateWithFoundRows(): void
    {
        $session = (new Instance())->connect();
        $session->variables->clientFoundRows = true;
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY); INSERT INTO t VALUES (1)');
        $reply = $session->query('INSERT INTO t VALUES (1) ON DUPLICATE KEY UPDATE id=id')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([1, 0], [$reply->affectedRows, $reply->lastInsertId]);
    }
}
