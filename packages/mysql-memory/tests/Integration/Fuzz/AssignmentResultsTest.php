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
            'LONGTEXT CHARACTER SET utf8mb4' => "'a'", 'LONGTEXT CHARACTER SET ucs2' => "'a'",
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
        yield 'nested hexadecimal assignment' => ['SELECT @outer:=(@inner:=0x41),(@outer:=(@inner:=0x41))+0'];
        yield 'collated hexadecimal assignment' => ['SELECT @v:=(0x41 COLLATE binary),(@v:=(0x41 COLLATE binary))+0'];
        yield 'date assignment arithmetic' => ["SELECT (@v:=DATE '2020-01-02')+0"];
        yield 'fractional temporal assignment arithmetic' => ["SELECT (@v:=TIME '12:34:56.12')+0,(@v:=TIMESTAMP '2020-01-02 12:34:56.123')+0"];
        yield 'string column assignment arithmetic' => ["CREATE TABLE assignment_values(v VARCHAR(5)); INSERT INTO assignment_values VALUES('12'); SELECT (@v:=v)+0 FROM assignment_values"];
        yield 'decimal variable expressions' => ['SET @v=1.25; SELECT @v,@v+0,@v*1,@v DIV 1,CAST(@v AS CHAR),CONCAT(@v),COERCIBILITY(@v)'];
        yield 'decimal variable materialization' => ['SET @v=1.25; SELECT * FROM (SELECT @v AS v LIMIT 1) d'];
        yield 'integer division widths' => ['SELECT CAST(1 AS DECIMAL(65,30)) DIV 1,CAST(1 AS DECIMAL(65,0)) DIV 1,CAST(1 AS CHAR(100)) DIV 1,1e0 DIV 1,1 DIV 1e0,CAST(1 AS UNSIGNED) DIV 1e0'];
        yield 'assigned decimal variable expressions' => ['SET @v=1.25; SELECT @w:=@v,(@w:=@v)+0,CAST(@w:=@v AS CHAR),CONCAT(@w:=@v)'];
        yield 'scalar variable read' => ['SELECT id,(@v:=id),(SELECT @v FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'row variable read' => ['SELECT id,(@v:=id),(1,1)=(SELECT @v,1 FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.')) {
            foreach (['ON', 'OFF'] as $setting) {
                yield 'timestamp defaults ' . $setting => ['SET explicit_defaults_for_timestamp=' . $setting . "; CREATE TABLE assignment_values(a TIMESTAMP, b TIMESTAMP NULL, c TIMESTAMP NOT NULL); INSERT INTO assignment_values VALUES('2020-01-02','2020-01-02','2020-01-02'); SELECT a,b,c,@v:=a FROM assignment_values"];
            }
        }
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
