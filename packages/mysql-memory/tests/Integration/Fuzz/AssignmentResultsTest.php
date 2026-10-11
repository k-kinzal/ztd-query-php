<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class AssignmentResultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $columns = [
            'TINYINT' => '1', 'SMALLINT' => '2', 'MEDIUMINT' => '3', 'INT' => '4', 'BIGINT' => '5',
            'INT UNSIGNED' => '6', 'DECIMAL(7,2) UNSIGNED' => '7.25', 'DECIMAL(7,2) ZEROFILL' => '7.25',
            'FLOAT ZEROFILL' => '0.5', 'FLOAT(8,2) ZEROFILL' => '12.34', 'DOUBLE(8,2) ZEROFILL' => '12.34', 'INT(2) ZEROFILL' => '2', 'DECIMAL(7,2)' => '7.25',
            'FLOAT' => '8.5', 'DOUBLE' => '9.5', 'FLOAT(8,2)' => '12.34', 'DOUBLE(8,2)' => '12.34',
            'VARCHAR(15)' => "'abc'", 'CHAR(5)' => "'x'", 'BINARY(5)' => "'x'",
            'TEXT CHARACTER SET latin1' => "'a'", 'TEXT CHARACTER SET ucs2' => "'a'", 'TEXT CHARACTER SET utf16' => "'a'", 'TEXT CHARACTER SET utf32' => "'a'",
            'TINYTEXT' => "'a'", 'TEXT' => "'b'", 'MEDIUMTEXT' => "'c'", 'LONGTEXT' => "'d'",
            "ENUM('a','b')" => "'a'", "SET('a','b')" => "'a,b'",
            'DATE' => "'2020-01-02'", 'TIME(2)' => "'12:34:56.12'",
            'TIMESTAMP(2)' => "'2020-01-02 12:34:56.12'", 'DATETIME(3)' => "'2020-01-02 01:02:03.456'",
            'YEAR' => '2020', 'BIT(3)' => '3', 'BIT(64)' => '0xffffffffffffffff',
        ];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.')) {
            $columns['JSON'] = "'{}'";
        }
        foreach ($columns as $type => $value) {
            yield $type => ['CREATE TABLE assignment_values (v ' . $type . '); INSERT INTO assignment_values VALUES (' . $value . '); SELECT v,@assigned:=v,CHARSET(@assigned:=v),COLLATION(@assigned:=v),COERCIBILITY(@assigned:=v) FROM assignment_values; SELECT @assigned'];
        }
        foreach (['NULL', '1', '1.25', '1e0', "'abc'", "b'1000001'", '0x41'] as $value) {
            yield 'literal ' . $value => ['SELECT @assigned:=' . $value . ',CHARSET(@assigned:=' . $value . '),COLLATION(@assigned:=' . $value . '),COERCIBILITY(@assigned:=' . $value . ')'];
        }
        yield 'bit numeric context' => ["SELECT (@v:=b'1000001')+0"];
        yield 'hexadecimal numeric context' => ['SELECT (@v:=0x41)+0'];
        yield 'scalar variable read' => ['SELECT id,(@v:=id),(SELECT @v FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'row variable read' => ['SELECT id,(@v:=id),(1,1)=(SELECT @v,1 FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
    }

    #[DataProvider('providerStatements')]
    public function testAssignmentResultsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
