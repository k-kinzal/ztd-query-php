<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Audit\Status;

use Fuzz\Structure\Audit\Status\Listing;
use Fuzz\Target\Status\Snapshot;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Exercises rejection boundaries of the direct status comparison contract.
 */
#[CoversNothing]
#[Small]
final class ListingTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function providerMemoryObservations(): iterable
    {
        $observation = ['results' => [['columns' => [['name' => 'Variable_name', 'type' => 253], ['name' => 'Value', 'type' => 253]], 'rows' => [['Bytes_received', '0'], ['Com_select', '2'], ['Handler_read_key', '0'], ['Queries', '8'], ['Threads_connected', '1']]]], 'warnings' => [], 'disconnected' => false];
        yield 'exact history and empty physical values' => [$observation, true];
        $changed = $observation;
        $changed['results'][0]['rows'][0][1] = '1';
        yield 'direct transport must be empty' => [$changed, false];
        $changed = $observation;
        $changed['results'][0]['rows'][1][1] = '3';
        yield 'session command history stays exact' => [$changed, false];
        $changed = $observation;
        $changed['results'][0]['rows'][2][1] = '1';
        yield 'physical memory quantity must be zero' => [$changed, false];
        $changed = $observation;
        $changed['results'][0]['rows'][3][1] = '9';
        yield 'global history delta stays exact' => [$changed, false];
        $changed = $observation;
        $changed['results'][0]['rows'][4][1] = '2';
        yield 'live session count stays exact' => [$changed, false];
        $changed = $observation;
        $changed['results'][0]['rows'][] = ['Unknown_new_counter', '1'];
        yield 'additional names are not hidden' => [$changed, false];
        $changed = $observation;
        $changed['results'][0]['columns'][1]['type'] = 8;
        yield 'metadata stays exact' => [$changed, false];
        $changed = $observation;
        $changed['warnings'] = [['Warning', '1', 'unexpected']];
        yield 'diagnostics stay exact' => [$changed, false];
        $changed = $observation;
        $changed['disconnected'] = true;
        yield 'termination stays exact' => [$changed, false];
    }

    /**
     * @param array<string, mixed> $memory A complete direct observation including deliberately incorrect alternatives
     */
    #[DataProvider('providerMemoryObservations')]
    public function testComparableDoesNotHideHistoryMetadataOrUnexpectedState(array $memory, bool $matches): void
    {
        $nativeValues = ['Bytes_received' => '100', 'Com_select' => '2', 'Handler_read_key' => '7', 'Queries' => '16', 'Threads_connected' => '1'];
        $memoryValues = ['Bytes_received' => '0', 'Com_select' => '2', 'Handler_read_key' => '0', 'Queries' => '6', 'Threads_connected' => '1'];
        $contract = new Listing(new Snapshot($nativeValues, $nativeValues, '0', '0', microtime(true)), new Snapshot($memoryValues, $memoryValues, '0', '0', microtime(true)), false, 'SHOW STATUS');
        $native = ['results' => [['columns' => [['name' => 'Variable_name', 'type' => 253], ['name' => 'Value', 'type' => 253]], 'rows' => [['Bytes_received', '116'], ['Com_select', '2'], ['Handler_read_key', '12'], ['Queries', '18'], ['Threads_connected', '1']]]], 'warnings' => [], 'disconnected' => false];

        self::assertSame($matches, $contract->comparable($native, false) === $contract->comparable($memory, true));
        self::assertSame([Listing::CONTRACT], $contract->comparable($native, false)['contracts'] ?? null);
    }
}
