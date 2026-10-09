<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Differential;
use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class DifferentialTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerWrites(): iterable
    {
        foreach ([
            'UPDATE t1 SET a = a',
            'UPDATE t1 SET a = 10 WHERE id <= 2',
            'UPDATE t1 SET a = a WHERE id < 0',
            'UPDATE t1 SET a = a ORDER BY id LIMIT 2',
            'UPDATE t1 JOIN t2 ON t1.id = t2.id SET t1.a = t1.a, t2.a = 10',
            'UPDATE t1 JOIN t2 ON t2.a > 0 SET t1.a = t1.a',
            'UPDATE IGNORE t2 SET name = \'x\'',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE name = \'x\'',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE name = \'changed\'',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE name = \'X\'',
            'INSERT INTO t2 (name) VALUES (\'new\') ON DUPLICATE KEY UPDATE name = \'new\'',
            'INSERT INTO t2 (name) VALUES (\'new\'), (\'other\')',
            'INSERT INTO t2 (id, name) VALUES (7, \'new\'), (8, \'other\')',
            'INSERT INTO t2 (id, name) VALUES (7, \'new\'), (1, \'x\') ON DUPLICATE KEY UPDATE name = name',
            'INSERT INTO t2 (name) VALUES (\'x\') ON DUPLICATE KEY UPDATE name = name',
            'INSERT INTO t2 (name) VALUES (\'x\') ON DUPLICATE KEY UPDATE name = \'changed\'',
            'INSERT IGNORE INTO t2 (name) VALUES (\'x\')',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
            'DELETE FROM t1 WHERE id < 3',
        ] as $sql) {
            foreach ([true, false] as $emulate) {
                foreach ([true, false] as $foundRows) {
                    yield $sql . ', emulate=' . (int) $emulate . ', found=' . (int) $foundRows => [$sql, $emulate, $foundRows];
                }
            }
        }
    }

    #[DataProvider('providerWrites')]
    public function testWriteCountsMatchTheServer(string $sql, bool $emulate, bool $foundRows): void
    {
        [$base] = Servers::shared();
        $target = new Differential($base->native, $base->nativeUser, $base->nativePassword, $base->memory, $emulate, $base->version, $base->guardUser, $foundRows);
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, $sql);
        self::assertNull($comparison->difference, $sql . "\n" . $comparison->difference);
    }

    public function testEveryResultOfAMultiStatementQueryMatchesTheServer(): void
    {
        $sql = 'INSERT INTO t2 (name) VALUES (\'new\'); SELECT LAST_INSERT_ID(), ROW_COUNT(); SELECT id FROM t1 ORDER BY id DESC LIMIT 2';
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerResolutionErrors(): iterable
    {
        yield 'IN operand before list' => ['SELECT missing IN ((1 = ALL (SELECT 1,2)), 3)'];
        yield 'single IN element' => ['SELECT missing NOT IN ((1 = ALL (SELECT 1,2))) FROM t1'];
        yield 'row with scalar quantified operator' => ['SELECT (missing,1) = ALL (SELECT 1,2,3)'];
        yield 'scalar quantified operator' => ['SELECT missing = ALL (SELECT 1,2)'];
        yield 'names in both quantified operands' => ['SELECT missing = ALL (SELECT missing2)'];
        yield 'names in both membership operands' => ['SELECT missing IN (SELECT missing2)'];
        yield 'separate predicates with equal widths' => ['SELECT 1 IN (SELECT 1,2), missing = ALL (SELECT 1,2)'];
        yield 'row membership width' => ['SELECT (1,2) IN (SELECT 1,2,3), missing'];
        yield 'window count before frame' => ['SELECT NTILE(missing) OVER (RANGE CURRENT ROW EXCLUDE CURRENT ROW)'];
        yield 'window name before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER missing'];
        yield 'following field before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER (), missing'];
        yield 'repeated window before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER () WINDOW w AS (), w AS ()'];
        yield 'window ordering before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER (ORDER BY 1)'];
        yield 'null treatment before counting edge' => ['SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER ()'];
        yield 'counting edge before row number' => ['SELECT NTH_VALUE(1,0) FROM LAST OVER ()'];
    }

    #[DataProvider('providerResolutionErrors')]
    public function testResolutionErrorsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    public function testCompareDistinguishesAVolatileReferenceFromAMatch(): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare('SELECT UUID()');

        self::assertTrue($comparison->volatile);
        self::assertNull($comparison->difference);
    }
}
