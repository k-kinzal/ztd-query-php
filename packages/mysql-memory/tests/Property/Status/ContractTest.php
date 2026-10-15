<?php

declare(strict_types=1);

namespace Tests\Property\Status;

use Fuzz\Target\Status\Listing;
use Fuzz\Target\Status\Policy;
use Fuzz\Target\Status\Snapshot;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * Observation exceptions must retain SQL history and refuse malformed or invented values.
 */
#[CoversNothing]
#[Small]
final class ContractTest extends TestCase
{
    /**
     * Filters and compound statements cannot enter the bounded contract.
     */
    public function testSelectsOnlyCompleteStatusStatements(): void
    {
        self::assertTrue(Listing::handles('SHOW STATUS;'));
        self::assertTrue(Listing::handles('show local status'));
        self::assertFalse(Listing::handles("SHOW STATUS LIKE 'Com_%'"));
        self::assertFalse(Listing::handles('SHOW STATUS; SELECT 1'));
        self::assertFalse(Listing::handles("SELECT 'SHOW GLOBAL STATUS'"));
    }

    /**
     * SQL history remains distinguishable after normalization.
     */
    public function testSessionCommandsStayExactAndGlobalCommandsKeepTheirDelta(): void
    {
        $before = new Snapshot(['Com_select' => '100'], ['Com_select' => '5'], '50', '25', microtime(true));
        $policy = new Policy();

        self::assertSame('5', $policy->value('Com_select', '5', false, true, $before, 'SHOW STATUS', 0));
        self::assertSame('6', $policy->value('Com_select', '6', false, true, $before, 'SHOW STATUS', 0));
        self::assertSame('2', $policy->value('Com_select', '102', true, true, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertSame('3', $policy->value('Com_select', '103', true, true, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertNull($policy->value('Com_select', '99', true, true, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertSame('new-value', $policy->value('Future_counter', 'new-value', true, true, $before, 'SHOW GLOBAL STATUS', 0));
    }

    /**
     * Traffic counters agree with independent packet measurements.
     */
    public function testWireQuantitiesRequireIndependentClientMeasurements(): void
    {
        $before = new Snapshot(['Bytes_sent' => '100', 'Bytes_received' => '200'], ['Bytes_sent' => '10', 'Bytes_received' => '20'], '50', '25', microtime(true));
        $policy = new Policy();

        self::assertSame('<measured-wire-bytes>', $policy->value('Bytes_sent', '35', false, true, $before, 'SHOW STATUS', 0));
        self::assertSame('<measured-wire-bytes>', $policy->value('Bytes_sent', '150', true, false, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertSame('<measured-wire-bytes>', $policy->value('Bytes_received', '36', false, true, $before, 'SHOW STATUS', 0));
        self::assertSame('<measured-wire-bytes>', $policy->value('Bytes_received', '247', true, false, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertNull($policy->value('Bytes_sent', '34', false, true, $before, 'SHOW STATUS', 0));
        self::assertNull($policy->value('Bytes_received', '248', true, false, $before, 'SHOW GLOBAL STATUS', 0));
    }

    /**
     * Clock normalization respects the unsigned counter domain and elapsed time.
     */
    public function testClockBoundsRejectInventedValuesAndPermitCounterWrap(): void
    {
        $before = new Snapshot(['Uptime' => '10'], [], '0', '0', microtime(true));
        $policy = new Policy();
        $wrapped = new Snapshot(['Uptime' => '18446744073709551615'], [], '0', '0', microtime(true));

        self::assertSame('<bounded-elapsed-time>', $policy->value('Uptime', '11', true, true, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertSame('<bounded-elapsed-time>', $policy->value('Uptime', '0', true, true, $wrapped, 'SHOW GLOBAL STATUS', 0));
        self::assertNull($policy->value('Uptime', '12', true, true, $before, 'SHOW GLOBAL STATUS', 0));
        self::assertNull($policy->value('Uptime', '18446744073709551626', true, true, $before, 'SHOW GLOBAL STATUS', 0));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidValues(): iterable
    {
        yield 'invented buffer pool' => ['Innodb_buffer_pool_pages_total', '8192'];
        yield 'invented query cache' => ['Qcache_free_memory', '1031832'];
        yield 'invented physical count' => ['Innodb_data_writes', '4'];
        yield 'negative physical count' => ['Innodb_data_writes', '-1'];
        yield 'nonnumeric physical count' => ['Innodb_data_writes', 'wrong'];
        yield 'invented TLS resource' => ['Current_tls_cert', 'some.pem'];
        yield 'invented key' => ['Rsa_public_key', 'not-a-key'];
    }

    /**
     * An unavailable physical resource cannot acquire a fabricated memory value.
     */
    #[DataProvider('providerInvalidValues')]
    public function testARefusedPhysicalValueLeavesTheWholeObservationRaw(string $name, string $value): void
    {
        $before = new Snapshot([$name => '0'], [$name => '0'], '0', '0', microtime(true));
        $observation = ['results' => [['columns' => [['Variable_name'], ['Value']], 'rows' => [[$name, $value]]]], 'warnings' => []];

        self::assertSame($observation, (new Listing($before, 'SHOW STATUS', false))->comparable($observation, true));
    }

    /**
     * Every status row must occur exactly once with a string value.
     */
    public function testMissingDuplicateAndMalformedRowsCannotBeNormalized(): void
    {
        $before = new Snapshot(['Innodb_data_writes' => '0'], ['Innodb_data_writes' => '0'], '0', '0', microtime(true));
        $missing = ['results' => [['columns' => [['Variable_name'], ['Value']], 'rows' => []]], 'warnings' => []];
        $duplicate = $missing;
        $duplicate['results'][0]['rows'] = [['Innodb_data_writes', '0'], ['Innodb_data_writes', '0']];
        $listing = new Listing($before, 'SHOW STATUS', false);

        self::assertSame($missing, $listing->comparable($missing, true));
        self::assertSame($duplicate, $listing->comparable($duplicate, true));
        self::assertNull(Snapshot::index([['x', 0]]));
        self::assertNull(Snapshot::index([['x', '0', 'extra']]));
    }

    /**
     * The value contract never changes metadata or diagnostic conditions.
     */
    public function testNormalizationPreservesAllMetadataAndConditions(): void
    {
        $before = new Snapshot(['Innodb_data_writes' => '0'], ['Innodb_data_writes' => '0'], '0', '0', microtime(true));
        $observation = ['results' => [['columns' => [['Variable_name', 'source', 'VAR_STRING', 99], ['Value', 'source', 'VAR_STRING', 999]], 'rows' => [['Innodb_data_writes', '0']]]], 'warnings' => [['Warning', '1', 'kept']]];
        $normalized = (new Listing($before, 'SHOW STATUS', false))->comparable($observation, true);

        self::assertSame([Listing::CONTRACT], $normalized['contracts']);
        self::assertIsArray($normalized['results']);
        self::assertIsArray($normalized['results'][0]);
        self::assertSame($observation['results'][0]['columns'], $normalized['results'][0]['columns']);
        self::assertSame($observation['warnings'], $normalized['warnings']);
    }

    /**
     * A lifetime event clock cannot change during a status listing or precede its first read.
     */
    public function testEventTimeRequiresUnchangedHistoryAndAClockAfterTheFirstRead(): void
    {
        $policy = new Policy();
        $empty = new Snapshot(['Deprecated_use_i_s_processlist_count' => '0', 'Deprecated_use_i_s_processlist_last_timestamp' => '0'], [], '0', '0', 0);
        $before = new Snapshot(['Deprecated_use_i_s_processlist_count' => '2', 'Deprecated_use_i_s_processlist_last_timestamp' => '1700000000000000'], [], '0', '0', 0);
        self::assertSame('<unchanged-server-event-time>', $policy->value('Deprecated_use_i_s_processlist_last_timestamp', '0', false, true, $empty, 'SHOW STATUS', 0));
        self::assertSame('<unchanged-server-event-time>', $policy->value('Deprecated_use_i_s_processlist_last_timestamp', '1700000000000000', false, true, $before, 'SHOW STATUS', 0));
        self::assertSame('1', $policy->value('Deprecated_use_i_s_processlist_count', '3', false, true, $before, 'SHOW STATUS', 0));
        self::assertNull($policy->value('Deprecated_use_i_s_processlist_last_timestamp', '1700000000000001', false, true, $before, 'SHOW STATUS', 0));
        self::assertNull($policy->value('Deprecated_use_i_s_processlist_last_timestamp', '18446744073709551616', true, true, $before, 'SHOW STATUS', 0));
        self::assertNull($policy->eventTime('0', '0', $before));
        self::assertNull($policy->eventTime('1700000000000000', '1700000000000000', $empty));
    }

}
