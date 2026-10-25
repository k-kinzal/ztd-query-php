<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Observation\Observer;
use Fuzz\Observation\ResultOrder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;

#[CoversNothing]
#[Large]
final class ObservationContractTest extends TestCase
{
    #[DataProvider('providerQueryOrdering')]
    public function testOrderingBelongsToTheOuterQuery(string $release, string $sql, bool $ordered): void
    {
        $tree = (new MySqlParser('mysql-' . $release))->parse($sql);

        self::assertSame($ordered, (new ResultOrder())->ordered($tree));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerQueryOrdering(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            foreach ([
                ['SELECT 1 ORDER BY 1', true],
                ['SELECT 1', false],
                ["SELECT 'order by'", false],
                ['SELECT 1 /* order by 1 */', false],
                ['SELECT * FROM (SELECT 1 ORDER BY 1) x', false],
                ['SELECT (SELECT 1 ORDER BY 1)', false],
                ['SELECT GROUP_CONCAT(a ORDER BY a) FROM t', false],
                ['(SELECT 1 ORDER BY 1)', true],
                ['(SELECT 1 ORDER BY 1) UNION SELECT 2', false],
                ['SELECT 1 UNION (SELECT 2 ORDER BY 1)', false],
                ['SELECT 1 UNION SELECT 2 ORDER BY 1', true],
                ['(SELECT 1) UNION (SELECT 2) ORDER BY 1', true],
                ['EXPLAIN SELECT 1 ORDER BY 1', true],
                ['CALL p()', true],
            ] as [$sql, $ordered]) {
                yield $release . ' ' . $sql => [$release, $sql, $ordered];
            }
            if (!str_starts_with($release, '5.')) {
                yield $release . ' window' => [$release, 'SELECT ROW_NUMBER() OVER (ORDER BY a) FROM t', false];
                yield $release . ' CTE' => [$release, 'WITH x AS (SELECT 1 ORDER BY 1) SELECT * FROM x', false];
                yield $release . ' ordered CTE query' => [$release, 'WITH x AS (SELECT 1) SELECT * FROM x ORDER BY 1', true];
            }
        }
    }

    public function testAnUnorderedResultStillRetainsDuplicatesAndValueTypes(): void
    {
        $observer = new Observer();

        self::assertSame($observer->bag([[2], ['1'], [2], [null]]), $observer->bag([[null], [2], ['1'], [2]]));
        self::assertNotSame($observer->bag([[2], ['1'], [2]]), $observer->bag([[2], ['1']]));
        self::assertNotSame($observer->bag([[1]]), $observer->bag([['1']]));
    }
}
