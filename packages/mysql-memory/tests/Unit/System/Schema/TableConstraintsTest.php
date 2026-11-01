<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Schema\TableConstraints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableConstraints::class)]
#[Small]
final class TableConstraintsTest extends TestCase
{
    public function testRowsListsKeysForeignKeysAndChecks(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");

        $result1 = $s->query("SELECT CONSTRAINT_NAME, TABLE_NAME, CONSTRAINT_TYPE, ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['PRIMARY', 'c', 'PRIMARY KEY', 'YES'], ['c_ibfk_1', 'c', 'FOREIGN KEY', 'YES'], ['fk_p', 'c', 'FOREIGN KEY', 'YES'], ['c_chk_1', 'c', 'CHECK', 'YES'], ['ck_t', 'c', 'CHECK', 'YES'], ['code', 'p', 'UNIQUE', 'YES'], ['PRIMARY', 'p', 'PRIMARY KEY', 'YES']], $result1->rows);
    }
}
