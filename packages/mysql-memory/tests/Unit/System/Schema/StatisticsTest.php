<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Schema\Statistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Statistics::class)]
#[Small]
final class StatisticsTest extends TestCase
{
    public function testRowsListsTheColumnsOfEachIndex(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");

        $result1 = $s->query("SELECT TABLE_NAME, NON_UNIQUE, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, COLLATION, CARDINALITY, SUB_PART, NULLABLE, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([
            ['c', '1', 'code', '1', 'code', 'A', '0', null, 'YES', 'BTREE'],
            ['c', '1', 'fk_p', '1', 'pid', 'A', '0', null, 'YES', 'BTREE'],
            ['c', '1', 'ft', '1', 't', null, '0', null, 'YES', 'FULLTEXT'],
            ['c', '0', 'PRIMARY', '1', 'id', 'A', '0', null, '', 'BTREE'],
            ['p', '0', 'code', '1', 'code', 'A', '2', null, '', 'BTREE'],
            ['p', '1', 'k_name', '1', 'name', 'A', '1', '10', 'YES', 'BTREE'],
            ['p', '1', 'k_name', '2', 'amount', 'D', '1', null, 'YES', 'BTREE'],
            ['p', '0', 'PRIMARY', '1', 'id', 'A', '2', null, '', 'BTREE'],
        ], $result1->rows);
    }
}
