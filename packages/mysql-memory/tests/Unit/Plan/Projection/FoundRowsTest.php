<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Plan\Projection\FoundRows::class)]
#[Small]
final class FoundRowsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerQueries(): iterable
    {
        yield 'ordinary select' => ['SELECT 1', false];
        yield 'calculated select' => ['SELECT SQL_CALC_FOUND_ROWS 1 LIMIT 0', true];
        yield 'calculated union' => ['SELECT SQL_CALC_FOUND_ROWS 1 UNION ALL SELECT 2 LIMIT 1', true];
        yield 'ordinary union' => ['SELECT 1 UNION SELECT 2 LIMIT 1', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerQueries')]
    public function testRequestedReadsTheFirstSelectAcrossSetAndLimitWrappers(string $sql, bool $expected): void
    {
        $query = (new \MySqlMemory\Instance())->connect()->analyze($sql)->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        $query = $query->query;

        self::assertSame($expected, (new \MySqlMemory\Plan\Projection\FoundRows())->requested($query));
    }

    public function testRequestedKeepsCalculatedConstantRowsDistinctFromDiscardedLegacyRows(): void
    {
        $session = (new \MySqlMemory\Instance('5.7.44'))->connect();
        $session->query('SELECT 1 LIMIT 1 OFFSET 9');
        self::assertSame(0, $session->variables->foundRows);
        $session->query('SELECT SQL_CALC_FOUND_ROWS 1 LIMIT 1 OFFSET 9');
        self::assertSame(1, $session->variables->foundRows);
    }

}
