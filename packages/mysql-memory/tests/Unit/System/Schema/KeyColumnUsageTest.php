<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Schema\KeyColumnUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeyColumnUsage::class)]
#[Small]
final class KeyColumnUsageTest extends TestCase
{
    public function testRowsListsTheColumnsOfKeysAndForeignKeys(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");

        $result1 = $s->query("SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, POSITION_IN_UNIQUE_CONSTRAINT, REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([
            ['PRIMARY', 'c', 'id', '1', null, null, null, null],
            ['c_ibfk_1', 'c', 'code', '1', '1', 'd', 'p', 'code'],
            ['fk_p', 'c', 'pid', '1', '1', 'd', 'p', 'id'],
            ['code', 'p', 'code', '1', null, null, null, null],
            ['PRIMARY', 'p', 'id', '1', null, null, null, null],
        ], $result1->rows);
    }
}
