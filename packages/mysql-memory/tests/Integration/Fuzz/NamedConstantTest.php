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
final class NamedConstantTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['TRUE', 'FALSE', '+TRUE', '-TRUE', '-FALSE', '-(TRUE)', '-+TRUE', '-(-TRUE)', '-(1+0)', 'TRUE COLLATE utf8mb4_bin', '(TRUE)', 'NULL', '1', '-1', '+1', '1+0', "X'61'", "B'1'", "'a' COLLATE utf8mb4_bin", '-(-1)', 'ABS(1)'] as $argument) {
            yield 'value ' . $argument => ["SELECT NAME_CONST('x', " . $argument . '); SHOW WARNINGS'];
            yield 'name ' . $argument => ['SELECT NAME_CONST(' . $argument . ', 1); SHOW WARNINGS'];
        }
        foreach (['1e3', '1e-3', '1e30', '1e14', '1e15', '1e-15', '1e-16', '1.2345678901234568e-5', "_utf8mb4 X'61'"] as $argument) {
            yield 'literal name ' . $argument => ['SELECT NAME_CONST(' . $argument . ', 1); SHOW WARNINGS'];
        }
        foreach (["DATE '2020-01-01'", "TIME '12:34:56'", "TIMESTAMP '2020-01-01 12:34:56'", "{d '2020-01-01'}", "DATE '20200101'", "TIME '12:34:56.123456'", "TIMESTAMP '2020-12-31 23:59:59.9999995'"] as $argument) {
            yield 'temporal name ' . $argument => ['SELECT NAME_CONST(' . $argument . ', 1); SHOW WARNINGS'];
        }
        foreach (['E9', 'E961', '61E9', '61E962', '00E961', '00610062', 'F09F9880', '61F09F9880', 'C3A9', 'C361', '612020', '206120', '7F61', 'C28061', 'C2A061', 'E3808061', 'C3A90062', 'E96161', 'F09F98', 'FF6161', '8061', 'EDB080'] as $hex) {
            yield 'name bytes ' . $hex => ["SELECT NAME_CONST(X'" . $hex . "', 1); SHOW WARNINGS"];
        }
        foreach (["NAME_CONST('a' COLLATE utf8mb4_bin, 1)", "NAME_CONST('x', 'a' COLLATE utf8mb4_bin)", 'NAME_CONST(TRUE COLLATE utf8mb4_bin, 1)', "NAME_CONST('x', TRUE COLLATE utf8mb4_bin)", 'NAME_CONST(missing, 1)', 'NAME_CONST(ABS(1), missing)', "NAME_CONST('x', missing)", 'TRUE COLLATE utf8mb4_bin', '1 COLLATE utf8mb4_bin', 'NULL COLLATE utf8mb4_bin'] as $expression) {
            yield 'latin1 resolution ' . $expression => ['SET NAMES latin1; SELECT ' . $expression . '; SHOW WARNINGS'];
        }
        yield 'negated boolean metadata' => ['SELECT -TRUE, -FALSE, -(TRUE), -+TRUE'];
        yield 'empty table' => ["SELECT NAME_CONST('x', TRUE) FROM t1 WHERE FALSE; SHOW WARNINGS"];
        yield 'preparation' => ["PREPARE named_constant FROM 'SELECT NAME_CONST(''x'', TRUE)'; SHOW WARNINGS"];
        yield 'argument count' => ["SELECT NAME_CONST('x'); SHOW WARNINGS"];
    }

    #[DataProvider('providerStatements')]
    public function testNamedConstantsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
