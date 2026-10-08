<?php

declare(strict_types=1);

namespace Tests\Unit\System\Schema;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Schema\ReferentialConstraints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferentialConstraints::class)]
#[Small]
final class ReferentialConstraintsTest extends TestCase
{
    public function testRowsListsTheForeignKeysInTheOrderTheyAreDeclared(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE p (id INT AUTO_INCREMENT PRIMARY KEY, code CHAR(3) NOT NULL UNIQUE, name VARCHAR(40), amount DECIMAL(8,2), KEY k_name (name(10), amount DESC))');
        $s->query("CREATE TABLE c (id BIGINT UNSIGNED NOT NULL, pid INT, code CHAR(3), qty SMALLINT CHECK (qty > 0), t TEXT, PRIMARY KEY (id), CONSTRAINT fk_p FOREIGN KEY (pid) REFERENCES p (id) ON DELETE CASCADE, FOREIGN KEY (code) REFERENCES p (code), CONSTRAINT ck_t CHECK (t <> 'z'), FULLTEXT KEY ft (t))");
        $s->query("INSERT INTO p (code) VALUES ('aaa'), ('bbb')");

        $result1 = $s->query("SELECT CONSTRAINT_NAME, UNIQUE_CONSTRAINT_NAME, MATCH_OPTION, UPDATE_RULE, DELETE_RULE, TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['fk_p', 'PRIMARY', 'NONE', 'NO ACTION', 'CASCADE', 'c', 'p'], ['c_ibfk_1', 'code', 'NONE', 'NO ACTION', 'NO ACTION', 'c', 'p']], $result1->rows);
    }
}
