<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\InsertNote;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertNote::class)]
#[Small]
final class InsertNoteTest extends TestCase
{
    public function testRowsRetainsEmptyInsertedRowsAndTheResolvedTarget(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); EXPLAIN REPLACE INTO t VALUES ROW(),ROW()');
        $result = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Note', '1003', 'replace into `d`.`t` values (),()']], $result->rows);
    }

    public function testValuesKeepsTheOriginalDefaultOperand(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT DEFAULT 7)');
        $statement = $session->analyze('INSERT INTO t VALUES(DEFAULT)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows::class, $statement);
        self::assertSame([[$statement->rows[0]->values[0]]], (new InsertNote())->values($statement));
    }

    public function testRowUsesTheSameNamesForSetListsAndDuplicateUpdates(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); EXPLAIN INSERT INTO t SET a=1 ON DUPLICATE KEY UPDATE a=2');
        $result = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Note', '1003', 'insert into `d`.`t` (`d`.`t`.`a`) values (1) on duplicate key update `d`.`t`.`a` = 2']], $result->rows);
    }

    public function testValuePreservesDefaultRequestsInTheNote(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT DEFAULT 7); EXPLAIN INSERT IGNORE INTO t(a) VALUES (DEFAULT)');
        $result = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Note', '1003', 'insert ignore into `d`.`t` (`d`.`t`.`a`) values (default)']], $result->rows);
    }
}
