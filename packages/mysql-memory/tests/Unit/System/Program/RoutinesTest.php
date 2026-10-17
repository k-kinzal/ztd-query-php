<?php

declare(strict_types=1);

namespace Tests\Unit\System\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Program\Routines;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Routines::class)]
#[Small]
final class RoutinesTest extends TestCase
{
    public function testRowsDescribesEachRoutine(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $s->query("CREATE PROCEDURE pr(IN x INT, OUT y VARCHAR(20) CHARSET latin1, INOUT z DECIMAL(5,2)) COMMENT 'proc' SELECT x INTO y");
        $s->query('CREATE FUNCTION fn(a BIGINT UNSIGNED, s TEXT) RETURNS VARCHAR(30) DETERMINISTIC NO SQL RETURN CONCAT(a, s)');

        $result1 = $s->query("SELECT ROUTINE_NAME, ROUTINE_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, CHARACTER_SET_NAME, DTD_IDENTIFIER, ROUTINE_DEFINITION, EXTERNAL_LANGUAGE, IS_DETERMINISTIC, SQL_DATA_ACCESS, SECURITY_TYPE, ROUTINE_COMMENT, DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['fn', 'FUNCTION', 'varchar', '30', '120', 'utf8mb4', 'varchar(30)', 'RETURN CONCAT(a, s)', 'SQL', 'YES', 'NO SQL', 'DEFINER', '', 'root@%'], ['pr', 'PROCEDURE', '', null, null, null, null, 'SELECT x INTO y', 'SQL', 'NO', 'CONTAINS SQL', 'DEFINER', 'proc', 'root@%']], $result1->rows);
    }
}
