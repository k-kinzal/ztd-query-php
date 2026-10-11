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
final class GrammarDiagnosticTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['BIT', 'INT', 'CHAR', 'BINARY', 'FLOAT', 'DECIMAL'] as $type) {
            foreach (['4294967295', '4294967296', '18446744073709551616'] as $width) {
                yield $type . $width => ['CREATE TABLE t_width(a ' . $type . '(' . $width . '))'];
            }
        }
        foreach (['BIT(65)', 'INT(256)', 'CHAR(256)', 'DECIMAL(66,2)', 'TIME(7)', 'BIT(4294967296)'] as $type) {
            yield 'add ' . $type => ['ALTER TABLE t1 ADD oversize ' . $type];
            yield 'modify ' . $type => ['ALTER TABLE t1 MODIFY a ' . $type];
            yield 'declare ' . $type => ['CREATE PROCEDURE p_width() BEGIN DECLARE x ' . $type . '; END'];
        }
        yield 'change width' => ['ALTER TABLE t1 CHANGE a changed BIT(65)'];
        yield 'multiple added columns' => ['ALTER TABLE t1 ADD (valid INT, invalid BIT(65))'];
        yield 'width before table resolution' => ['ALTER TABLE missing_width ADD invalid BIT(65)'];
        yield 'width before column resolution' => ['ALTER TABLE t1 MODIFY missing_width BIT(65)'];
        yield 'alter warnings before failure' => ['ALTER TABLE t1 ADD first_width INT(11), ADD invalid BIT(65), ADD later_width INT(11)'];
        yield 'declare warnings before failure' => ['CREATE PROCEDURE p_width() BEGIN DECLARE first_width INT(11); DECLARE invalid BIT(65); DECLARE later_width INT(11); END'];
        yield 'declare width before default' => ['CREATE PROCEDURE p_width() BEGIN DECLARE invalid INT(256) DEFAULT !1; END'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'alter default before type failure' => ['ALTER TABLE t1 ADD (first_width INT(11), invalid BIT(65) DEFAULT (!1), later_width INT(11))'];
        }
        yield 'unsigned return width' => ['CREATE FUNCTION f_width() RETURNS BIT(18446744073709551615) RETURN 1'];
        yield 'decimal return width' => ['CREATE FUNCTION f_width() RETURNS BIT(18446744073709551616) RETURN 1'];
        yield 'names expression' => ['SET NAMES = USER ( )'];
        yield 'names resolution' => ['SET NAMES = missing_column'];
        yield 'names warning order' => ['SET NAMES = !1, @a = !2'];
        yield 'names assignment spelling' => ["SET @a = 'NAMES = 1', NAMES\n/* marker */ := 'utf8'"];
        yield 'names variable' => ['CREATE PROCEDURE p_names() BEGIN DECLARE names INT; SET NAMES = 1; END'];
        yield 'quoted names variable' => ['CREATE PROCEDURE p_names() BEGIN DECLARE names INT; SET `names` = 1; END'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'decimal connection' => ['EXPLAIN ANALYZE FOR CONNECTION 18446744073709551616'];
            yield 'leading zero connection' => ['EXPLAIN FOR CONNECTION 00018446744073709551616'];
            yield 'explain format first' => ['EXPLAIN FORMAT=yaml FOR CONNECTION 1e2'];
            yield 'decimal spatial id' => ['CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 18446744073709551616'];
            yield 'unsigned spatial id' => ['CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 18446744073709551615'];
            yield 'floating spatial id' => ['CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 1e2'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testGrammarDiagnosticsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
