<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Server\MemoryDatabase;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversNothing]
#[Large]
final class PartitionFailureBoundaryTest extends TestCase
{
    #[DataProvider('providerProtocols')]
    public function testMissingExchangeImplementationReachesTheFailureChannel(bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7', ['d']);
        try {
            $client = new PDO($server->dsn('d'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            self::assertSame(0, $client->exec('CREATE TABLE t (id INT) PARTITION BY HASH(id) PARTITIONS 2'));
            self::assertSame(0, $client->exec('CREATE TABLE replacement (id INT)'));
            self::assertFalse($client->query('ALTER TABLE t EXCHANGE PARTITION p0 WITH TABLE replacement'));
            self::assertContains($client->errorInfo()[1], [2006, 2013]);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('failure LogicException: No partition alteration for ');
            $server->assertNoInternalFailure();
        } finally {
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
