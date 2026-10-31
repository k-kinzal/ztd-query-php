<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Target\ExecutionTarget;
use MySqlMemory\Instance;
use MySqlMemory\System\Performance\InnoDbMonitor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class EngineStatusComparisonTest extends TestCase
{
    #[DataProvider('providerChanges')]
    public function testComparisonValidatesPhysicalContentAndRetainsLogicalDifferences(string $before, string $after, bool $matching): void
    {
        $report = (new InnoDbMonitor())->report(new Instance('8.4.7'));
        $expected = ['columns' => [], 'rows' => [['InnoDB', '', $report]], 'affected' => 1, 'insertId' => 0, 'warnings' => 0, 'info' => null];
        $actual = $expected;
        $actual['rows'][0][2] = str_replace($before, $after, $report);
        self::assertSame($matching, ExecutionTarget::engines($expected, $actual));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerChanges(): iterable
    {
        yield 'physical allocation' => ['Dictionary memory allocated 0', 'Dictionary memory allocated 548733', true];
        yield 'physical counter' => ['0 OS file reads', '1068 OS file reads', true];
        yield 'negative physical counter' => ['0 OS file reads', '-1 OS file reads', false];
        yield 'overflow physical counter' => ['0 OS file reads', '18446744073709551616 OS file reads', false];
        yield 'arbitrary physical text' => ['0 OS file reads', 'unknown OS file reads', false];
        yield 'user rows' => ['Number of rows inserted 0', 'Number of rows inserted 1', false];
        yield 'read views' => ['0 read views open', '1 read views open', false];
        yield 'missing section' => ["---\nLOG\n---\n", '', false];
        yield 'unknown field' => ["FILE I/O\n--------\n", "FILE I/O\n--------\nunknown field 7\n", false];
        yield 'missing terminator' => ['END OF INNODB MONITOR OUTPUT', 'END OF REPORT', false];
    }

    public function testComparisonRetainsEveryAllocationRowAndReplyField(): void
    {
        $expected = ['columns' => [['name' => 'Status']], 'rows' => [['performance_schema', 'threads.size', '256']], 'affected' => 1, 'insertId' => 0, 'warnings' => 0, 'info' => null];
        $actual = $expected;
        self::assertTrue(ExecutionTarget::engines($expected, $actual));
        $actual['rows'][0][2] = '0';
        self::assertFalse(ExecutionTarget::engines($expected, $actual));
        $actual = $expected;
        $actual['warnings'] = 1;
        self::assertFalse(ExecutionTarget::engines($expected, $actual));
        $actual = $expected;
        $actual['rows'] = [];
        self::assertFalse(ExecutionTarget::engines($expected, $actual));
        $actual = $expected;
        $actual['rows'][0][1] = 'threads.count';
        self::assertFalse(ExecutionTarget::engines($expected, $actual));
    }
}
