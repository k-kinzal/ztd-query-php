<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Observation\Status\Environment;
use Fuzz\Observation\Status\Listing;
use Fuzz\Observation\Status\Policy;
use Fuzz\Observation\Status\Snapshot;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StatusObservationTest extends TestCase
{
    public function testUnstartedBufferLoadingIsDistinctFromMissingOrUnknownStatus(): void
    {
        $environment = new Environment();

        self::assertSame('<environment:buffer-load>', $environment->value('Innodb_buffer_pool_load_status', 'not started'));
        self::assertNull($environment->value('Innodb_buffer_pool_load_status', ''));
        self::assertNull($environment->value('Innodb_buffer_pool_load_status', 'unknown state'));
        self::assertSame('not started', $environment->value('Unclassified_status', 'not started'));
    }

    #[DataProvider('providerListings')]
    public function testStatusTrafficIncludesProbesAndPreparationWithoutPredictingPackets(string $version, bool $emulate, bool $global): void
    {
        $server = Testcontainers::run(new DisposableMySql($version));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $connection = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $sql = $global ? 'SHOW GLOBAL STATUS' : 'SHOW SESSION STATUS';
            $record = new Record('native', $sql, '');
            $listing = Listing::capture($connection, $sql, $record);
            $observer = new Observer();
            $observer->probe($connection, "SELECT REPEAT('x', 1024)", $record);
            $raw = $observer->observe($connection, $sql, true, $record, $listing->traffic);
            $values = Snapshot::index($observer->rowset($raw));
            self::assertNotNull($values);
            $policy = new Policy();
            $elapsed = microtime(true) - $listing->before->instant;

            self::assertSame('<measured-wire-bytes>', $policy->value('Bytes_sent', $values['Bytes_sent'], $global, $listing->before, $listing->traffic, $elapsed));
            self::assertSame('<measured-wire-bytes>', $policy->value('Bytes_received', $values['Bytes_received'], $global, $listing->before, $listing->traffic, $elapsed), var_export([
                'baseline' => ($global ? $listing->before->global : $listing->before->session)['Bytes_received'],
                'observed' => $values['Bytes_received'],
                'before' => $global ? $listing->before->globalTraffic : $listing->before->sessionTraffic,
                'target' => $listing->traffic,
            ], true));
            self::assertNull($policy->value('Bytes_sent', '0', $global, $listing->before, $listing->traffic, $elapsed));
            self::assertNull($policy->value('Bytes_received', '0', $global, $listing->before, $listing->traffic, $elapsed));
            $comparable = $listing->comparable($raw);
            self::assertSame([Listing::CONTRACT], $comparable['contracts'] ?? null, var_export(array_filter($values, fn (string $value, string $name): bool => $policy->value($name, $value, $global, $listing->before, $listing->traffic, $elapsed) === null, ARRAY_FILTER_USE_BOTH), true));
            self::assertIsArray($raw['results']);
            self::assertIsArray($raw['results'][0]);
            self::assertIsArray($comparable['results']);
            self::assertIsArray($comparable['results'][0]);
            self::assertSame($raw['results'][0]['columns'], $comparable['results'][0]['columns']);
            self::assertSame($raw['warnings'], $comparable['warnings']);
            self::assertCount(count($values), $observer->rowset($comparable));
            self::assertSame($raw, $record->raw);
            self::assertCount(3, $record->probes);
            self::assertArrayHasKey('wire', $record->probes[0]->measurements);
            self::assertArrayHasKey('wire', $record->probes[1]->measurements);
            self::assertSame(['execution' => $listing->traffic->execution, 'completion' => $listing->traffic->completion], $record->measurements['wire']);
            self::assertSame(serialize($record), serialize(unserialize(serialize($record), ['allowed_classes' => [Record::class]])));
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        } finally {
            $connection = null;
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerListings(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach ([true, false] as $emulate) {
                foreach ([true, false] as $global) {
                    yield $version . ($emulate ? ', text' : ', prepared') . ($global ? ', global' : ', session') => [$version, $emulate, $global];
                }
            }
        }
    }
}
