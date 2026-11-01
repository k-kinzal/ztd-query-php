<?php

declare(strict_types=1);

namespace Tests\Integration;

use Fuzz\Target\ExecutionTarget;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class GlobalStatusComparisonTest extends TestCase
{
    #[DataProvider('providerCounters')]
    public function testInitializationCountsVaryOnlyAtGlobalScope(string $name, string $value, bool $global, bool $matching): void
    {
        $expected = ['columns' => [], 'rows' => [[$name, '35']], 'affected' => 1, 'insertId' => 0, 'warnings' => 0, 'info' => null];
        $actual = $expected;
        $actual['rows'][0][1] = $value;
        self::assertSame($matching, ExecutionTarget::status($expected, $actual, $global));
    }

    /**
     * @return iterable<string, array{string, string, bool, bool}>
     */
    public static function providerCounters(): iterable
    {
        yield 'global initialization' => ['Com_create_table', '0', true, true];
        yield 'session count' => ['Com_create_table', '0', false, false];
        yield 'global questions' => ['Questions', '0', true, true];
        yield 'session questions' => ['Questions', '0', false, false];
        yield 'other global command' => ['Com_insert', '0', true, false];
        yield 'unknown variable' => ['Unknown_counter', '0', true, false];
        yield 'negative' => ['Com_create_table', '-1', true, false];
        yield 'overflow' => ['Com_create_table', '18446744073709551616', true, false];
        yield 'invalid type' => ['Com_create_table', '35.0', true, false];
    }
}
