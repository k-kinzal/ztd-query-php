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
