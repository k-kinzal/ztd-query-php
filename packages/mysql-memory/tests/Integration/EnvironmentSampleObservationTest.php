<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\Observer;
use Fuzz\Observation\Password\Retention;
use Fuzz\Observation\Process;
use Fuzz\Observation\RandomPasswords;
use Fuzz\Observation\Record;
use Fuzz\Observation\TableTimes;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class EnvironmentSampleObservationTest extends TestCase
{
    #[DataProvider('providerProtocols')]
    public function testProcessAndTableClockSamplesRetainTheirInputs(bool $emulate): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $connection = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $guard = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connection->exec('SET GLOBAL event_scheduler=OFF');
            $connection->exec('SET timestamp = 1700000000');
            $record = new Record('native', 'SHOW PROCESSLIST', '');
            Process\Listing::prepare($connection, $record);
            $listing = Process\Listing::capture($connection, $guard, $record);
            $observer = new Observer();
            $raw = $observer->observe($connection, $record->sql, true, $record);
            $comparable = $listing->comparable($connection, $raw, $record);

            self::assertSame('OFF', $listing->before->scheduler);
            self::assertArrayHasKey($listing->before->current, $listing->before->rows);
            self::assertNotNull($listing->guard);
            self::assertArrayHasKey($listing->guard, $listing->before->rows);
            self::assertSame([Process\Listing::CONTRACT], $comparable['contracts'] ?? null, var_export(['before' => $listing->before, 'raw' => $raw, 'after' => array_slice($record->probes, -2)], true));
            self::assertCount(6, $record->probes);
            self::assertSame($raw, $record->raw);
            $clock = new TableTimes();
            $clock->execute($connection, 'CREATE TABLE t1 (id INT)', $record);
            $clock->execute($connection, 'INSERT INTO t1 VALUES (1)', $record);
            self::assertArrayHasKey('t1', $clock->creations);
            self::assertArrayHasKey('t1', $clock->intervals);
            self::assertTrue($clock->contains('t1', $clock->creations['t1'][0], true));
            self::assertTrue($clock->contains('t1', $clock->intervals['t1'][1]));
            self::assertCount(12, $record->probes);
            self::assertSame('CREATE TABLE t1 (id INT)', $record->probes[7]->sql);
            self::assertSame('INSERT INTO t1 VALUES (1)', $record->probes[10]->sql);
            self::assertSame($raw, $record->raw);
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        } finally {
            $guard = null;
            $connection = null;
            $server->stop();
        }
    }

    #[DataProvider('providerProtocols')]
    public function testRandomPasswordValidationRetainsCredentialAndReplacementEvidence(bool $emulate): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $connection = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $connection->exec('SET SESSION generated_random_password_length=37');
            $record = new Record('native', 'ALTER USER CURRENT_USER IDENTIFIED BY RANDOM PASSWORD RETAIN CURRENT PASSWORD', '');
            $contract = RandomPasswords::capture($connection, true, $record);
            $observer = new Observer();
            $raw = $observer->observe($connection, $record->sql, true, $record);
            $comparable = $contract->comparable($connection, $raw, $record);

            self::assertSame(37, $contract->length);
            self::assertSame([RandomPasswords::CONTRACT, Retention::CONTRACT], $comparable['contracts'] ?? null);
            self::assertSame($raw, $record->raw);
            self::assertCount(9, $record->probes);
            self::assertSame('SELECT @@generated_random_password_length, CURRENT_USER()', $record->probes[0]->sql);
            self::assertSame('ALTER USER CURRENT_USER DISCARD OLD PASSWORD', $record->probes[7]->sql);
            self::assertSame(serialize($record), serialize(unserialize(serialize($record), ['allowed_classes' => [Record::class]])));
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        } finally {
            $connection = null;
            $server->stop();
        }
    }

    #[DataProvider('providerProtocols')]
    public function testAnEmptyPrimaryCredentialIsAValidSample(bool $emulate): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $connection = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $connection->exec("ALTER USER CURRENT_USER IDENTIFIED BY ''");
            $record = new Record('native', 'ALTER USER CURRENT_USER IDENTIFIED BY RANDOM PASSWORD RETAIN CURRENT PASSWORD', '');
            $contract = RandomPasswords::capture($connection, true, $record);
            self::assertNotNull($contract->retention);
            self::assertSame('', $contract->retention->primary);
            self::assertCount(2, $record->probes);
            $raw = (new Observer())->observe($connection, $record->sql, true, $record);
            self::assertArrayHasKey('error', $raw);
            self::assertSame($raw, $contract->comparable($connection, $raw, $record));
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        } finally {
            $connection = null;
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerProtocols(): iterable
    {
        yield 'text' => [true];
        yield 'prepared' => [false];
    }
}
