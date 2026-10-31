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
final class SystemFailureBoundaryTest extends TestCase
{
    #[DataProvider('providerRequests')]
    public function testUnimplementedSystemOperationsReachTheIndependentFailureChannel(string $sql, string $missing, bool $emulate): void
    {
        $server = MemoryDatabase::start('8.4.7');
        try {
            $client = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_EMULATE_PREPARES => $emulate]);
            self::assertFalse($client->query($sql));
            self::assertContains($client->errorInfo()[1], [2006, 2013]);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('failure LogicException: No system table ' . $missing);

            $server->assertNoInternalFailure();
        } finally {
            $server->stop();
        }
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerRequests(): iterable
    {
        foreach ([true, false] as $emulate) {
            $protocol = $emulate ? 'text' : 'prepared';
            yield $protocol . ' read' => ['SELECT THREAD_ID FROM performance_schema.threads', 'row provider for performance_schema.threads', $emulate];
            yield $protocol . ' write' => ["UPDATE performance_schema.setup_instruments SET ENABLED='YES'", 'UPDATE implementation for performance_schema.setup_instruments', $emulate];
            yield $protocol . ' definition' => ['TRUNCATE TABLE mysql.servers', 'definition or lock implementation for mysql.servers', $emulate];
        }
    }
}
