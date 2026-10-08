<?php

declare(strict_types=1);

namespace Tests\Unit\System\Program;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Program\Parameters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parameters::class)]
#[Small]
final class ParametersTest extends TestCase
{
    public function testRowsDescribesEachParameterAndReturnedValue(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $s->query("CREATE PROCEDURE pr(IN x INT, OUT y VARCHAR(20) CHARSET latin1, INOUT z DECIMAL(5,2)) COMMENT 'proc' SELECT x INTO y");
        $s->query('CREATE FUNCTION fn(a BIGINT UNSIGNED, s TEXT) RETURNS VARCHAR(30) DETERMINISTIC NO SQL RETURN CONCAT(a, s)');

        $result1 = $s->query("SELECT SPECIFIC_NAME, ORDINAL_POSITION, PARAMETER_MODE, PARAMETER_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, DTD_IDENTIFIER, ROUTINE_TYPE FROM information_schema.PARAMETERS WHERE SPECIFIC_SCHEMA = 'd'")[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([
            ['fn', '0', null, null, 'varchar', '30', 'varchar(30)', 'FUNCTION'],
            ['fn', '1', 'IN', 'a', 'bigint', null, 'bigint unsigned', 'FUNCTION'],
            ['fn', '2', 'IN', 's', 'text', '65535', 'text', 'FUNCTION'],
            ['pr', '1', 'IN', 'x', 'int', null, 'int', 'PROCEDURE'],
            ['pr', '2', 'OUT', 'y', 'varchar', '20', 'varchar(20)', 'PROCEDURE'],
            ['pr', '3', 'INOUT', 'z', 'decimal', null, 'decimal(5,2)', 'PROCEDURE'],
        ], $result1->rows);
    }
}
