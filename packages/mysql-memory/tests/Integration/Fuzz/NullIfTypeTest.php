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
final class NullIfTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['TINYINT' => '1', 'SMALLINT' => '1', 'MEDIUMINT' => '1', 'INT' => '1', 'BIGINT' => '1', 'INT UNSIGNED' => '1', 'BIGINT UNSIGNED' => '18446744073709551615', 'BIT(8)' => '65', 'YEAR' => '2020', 'DECIMAL(8,2)' => '1.25', 'FLOAT' => '1.25', 'DOUBLE' => '1.25', 'FLOAT(8,2)' => '1.25', 'DOUBLE(9,3)' => '1.25', 'DATE' => "'2020-01-01'", 'TIME(3)' => "'-12:01:02.345'", 'DATETIME(3)' => "'2020-01-01 12:01:02.345'", 'TIMESTAMP(3) NULL' => "'2020-01-01 12:01:02.345'"] as $type => $value) {
            $queries = [];
            foreach (['NULLIF(a,NULL)', 'NULLIF(MIN(a),NULL)', "CONCAT(NULLIF(a,NULL),'')", 'NULLIF(a,a)', 'NULLIF(a,NULL)+0', 'CHARSET(NULLIF(a,NULL))', 'COERCIBILITY(NULLIF(a,NULL))'] as $expression) {
                $queries[] = "SELECT {$expression} FROM nullif_probe";
            }
            yield $type => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE nullif_probe(a {$type}); INSERT INTO nullif_probe VALUES({$value}),(NULL); " . implode('; ', $queries)];
        }
        foreach (['1e0', '1.23e2', "CAST('2020-01-01' AS DATE)", "CAST('12:01:02.345' AS TIME(3))", "CAST('2020-01-01 12:01:02.345' AS DATETIME(3))"] as $argument) {
            yield "computed: {$argument}" => ["SET NAMES utf8mb4; SELECT NULLIF({$argument},NULL), NULLIF({$argument},NULL)+0, CHARSET(NULLIF({$argument},NULL))"];
        }
        foreach (['a+0', 'a+9999999', 'a+a', 'a-a', 'a*a', 'a%2', 'a+b', 'a*b', 'a%b', 'a/2', 'a/b', 'a+c', 'a/c'] as $expression) {
            yield "fixed arithmetic: {$expression}" => ["CREATE TEMPORARY TABLE nullif_probe(a FLOAT(8,2),b DOUBLE(9,3),c DOUBLE); INSERT INTO nullif_probe VALUES(1.25,2.375,1.25); SELECT {$expression} FROM nullif_probe"];
        }
        foreach (['DATE' => "'2020-01-01'", 'TIME(3)' => "'12:01:02.345'", 'DATETIME(3)' => "'2020-01-01 12:01:02.345'", 'YEAR' => '2020'] as $type => $value) {
            $setup = "SET NAMES utf8mb4; CREATE TEMPORARY TABLE nullif_probe(a {$type}); INSERT INTO nullif_probe VALUES({$value}); ";
            yield "derived: {$type}" => [$setup . 'SELECT x,x+0,CHARSET(x),COERCIBILITY(x) FROM (SELECT NULLIF(a,NULL) x FROM nullif_probe LIMIT 1) d'];
            yield "scalar: {$type}" => [$setup . 'SELECT (SELECT NULLIF(a,NULL) FROM nullif_probe), (SELECT NULLIF(a,NULL) FROM nullif_probe)+0'];
            yield "union: {$type}" => [$setup . 'SELECT NULLIF(a,NULL) x FROM nullif_probe UNION SELECT NULLIF(a,NULL) FROM nullif_probe'];
            yield "create: {$type}" => [$setup . 'CREATE TEMPORARY TABLE nullif_stored AS SELECT NULLIF(a,NULL) x FROM nullif_probe; SELECT x,x+0,CHARSET(x),COERCIBILITY(x) FROM nullif_stored; SHOW CREATE TABLE nullif_stored'];
        }
        foreach (['a', "CONCAT(a,'')", 'NULLIF(a,NULL)', "_latin1'a' COLLATE latin1_bin"] as $expression) {
            yield "stored collation: {$expression}" => ["SET NAMES utf8mb4; CREATE TABLE nullif_probe(a VARCHAR(10) CHARACTER SET latin1); INSERT INTO nullif_probe VALUES('a'); CREATE TABLE nullif_stored AS SELECT {$expression} x FROM nullif_probe; SHOW CREATE TABLE nullif_stored; CREATE TABLE nullif_copy LIKE nullif_stored; SHOW CREATE TABLE nullif_copy; ALTER TABLE nullif_stored ADD COLUMN y INT; SHOW CREATE TABLE nullif_stored; CREATE TABLE nullif_recopied AS SELECT x FROM nullif_stored; SHOW CREATE TABLE nullif_recopied; ALTER TABLE nullif_stored MODIFY x VARCHAR(10) CHARACTER SET latin1; SHOW CREATE TABLE nullif_stored"];
        }
        foreach (['YEAR', 'TINYINT UNSIGNED', 'INT UNSIGNED', 'BIGINT UNSIGNED', 'TINYINT', 'INT'] as $type) {
            $queries = [];
            foreach (['a', 'NULLIF(a,NULL)'] as $input) {
                $queries[] = "SELECT {$input}+100000, {$input}+1000000000000, {$input}%2, -{$input}, {$input}*2, MOD({$input},2) FROM nullif_probe";
            }
            yield "integer widths: {$type}" => ["CREATE TEMPORARY TABLE nullif_probe(a {$type}); INSERT INTO nullif_probe VALUES(0); " . implode('; ', $queries)];
        }
        yield 'reported YEAR arithmetic' => ['CREATE TEMPORARY TABLE nullif_probe(a YEAR); INSERT INTO nullif_probe VALUES(2020); SELECT NULLIF(a,NULL)+0, NULLIF(a,NULL)+100000, NULLIF(a,NULL)-1, NULLIF(a,NULL)*2, NULLIF(a,NULL)%2, NULLIF(a,NULL)/2, NULLIF(a,NULL) DIV 2, -NULLIF(a,NULL), ABS(NULLIF(a,NULL)) FROM nullif_probe'];
        yield 'fixed MOD function' => ['CREATE TEMPORARY TABLE nullif_probe(a FLOAT(8,2),b DOUBLE(9,3)); INSERT INTO nullif_probe VALUES(1.25,2.375); SELECT MOD(a,b), MOD(b,a) FROM nullif_probe'];
        foreach (['DECIMAL(31,30)', 'DECIMAL(35,30)', 'DECIMAL(35,28)'] as $type) {
            $queries = [];
            foreach ([0, 2, 4, 6] as $increment) {
                $queries[] = "SET div_precision_increment={$increment}; SELECT a/2 FROM nullif_probe";
            }
            yield "capped division scale: {$type}" => ["CREATE TEMPORARY TABLE nullif_probe(a {$type}); INSERT INTO nullif_probe VALUES(0.1); " . implode('; ', $queries)];
        }
        foreach (['FLOAT', 'DOUBLE', 'FLOAT(8,2)', 'DOUBLE(9,3)'] as $type) {
            yield "extrema: {$type}" => ["SET NAMES utf8mb4; CREATE TEMPORARY TABLE nullif_probe(a {$type}); INSERT INTO nullif_probe VALUES(1.25); SELECT MIN(a),MAX(a),NULLIF(MIN(a),NULL),CONCAT(MIN(a),'') FROM nullif_probe"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testReturnTypesAndEvaluationContextsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
