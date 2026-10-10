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
