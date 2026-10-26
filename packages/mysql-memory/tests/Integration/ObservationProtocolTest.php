<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Fuzz\Container\DisposableMySql;
use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Observation\Traffic;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ObservationProtocolTest extends TestCase
{
    #[DataProvider('providerModes')]
    public function testPreparationAndExecutionRefusalsRemainSqlOutcomes(int $mode, bool $emulate): void
    {
        $server = Testcontainers::run(new DisposableMySql('8.4.7'));
        try {
            $endpoint = $server->getData(Endpoint::class);
            $connection = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => $mode, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            $connection->exec('CREATE TABLE observed (id INT PRIMARY KEY)');
            $connection->exec('INSERT INTO observed VALUES (1)');
            $observer = new Observer();
            $unbound = new Record('native', 'SELECT missing FROM observed', '');
            $unboundTraffic = new Traffic();
            $refusal = $observer->observe($connection, $unbound->sql, true, $unbound, $unboundTraffic);

            self::assertSame([], $refusal['results']);
            self::assertIsArray($refusal['error']);
            self::assertSame([1054, '42S22'], array_slice($refusal['error'], 0, 2));
            self::assertNotEmpty($refusal['warnings']);
            self::assertSame($refusal, $unbound->raw);
            self::assertSame($emulate, $unboundTraffic->execution !== null);
            self::assertNotNull($unboundTraffic->completion);
            $duplicate = new Record('native', 'INSERT INTO observed VALUES (1)', '');
            $duplicateTraffic = new Traffic();
            $conflict = $observer->observe($connection, $duplicate->sql, true, $duplicate, $duplicateTraffic);

            self::assertSame([], $conflict['results']);
            self::assertIsArray($conflict['error']);
            self::assertSame([1062, '23000'], array_slice($conflict['error'], 0, 2));
            self::assertNotEmpty($conflict['warnings']);
            self::assertSame($conflict, $duplicate->raw);
            self::assertNotNull($duplicateTraffic->execution);
            self::assertNotNull($duplicateTraffic->completion);
            self::assertSame(['results' => [['affected' => 1, 'lastInsertId' => '0']], 'warnings' => []], $observer->observe($connection, 'UPDATE observed SET id=2', true));
            self::assertSame($emulate, $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES));
            self::assertSame($mode, $connection->getAttribute(PDO::ATTR_ERRMODE));
        } finally {
            $connection = null;
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function providerModes(): iterable
    {
        yield 'exception, text' => [PDO::ERRMODE_EXCEPTION, true];
        yield 'exception, prepared' => [PDO::ERRMODE_EXCEPTION, false];
        yield 'silent, text' => [PDO::ERRMODE_SILENT, true];
        yield 'silent, prepared' => [PDO::ERRMODE_SILENT, false];
    }
}
