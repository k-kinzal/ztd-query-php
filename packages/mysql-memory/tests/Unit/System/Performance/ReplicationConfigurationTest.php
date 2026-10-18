<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Performance\ReplicationConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReplicationConfiguration::class)]
#[Small]
final class ReplicationConfigurationTest extends TestCase
{
    public function testRowsKeepPartialConnectionUpdatesUntilResetAll(): void
    {
        $session = (new Instance())->connect();
        $session->run("CHANGE REPLICATION SOURCE TO SOURCE_HOST='local',SOURCE_USER='u',SOURCE_COMPRESSION_ALGORITHMS='bad'");
        $sql = 'SELECT HOST,USER,COMPRESSION_ALGORITHM,CONNECTION_RETRY_COUNT FROM performance_schema.replication_connection_configuration';
        $before = $session->query($sql)[0];
        $session->query('RESET REPLICA');
        $retained = $session->query($sql)[0];
        $session->query('RESET REPLICA ALL');
        $removed = $session->query($sql)[0];
        self::assertInstanceOf(ResultSet::class, $before);
        self::assertInstanceOf(ResultSet::class, $retained);
        self::assertInstanceOf(ResultSet::class, $removed);
        self::assertSame([['local', 'u', 'uncompressed', '10']], $before->rows);
        self::assertSame($before->rows, $retained->rows);
        self::assertSame([], $removed->rows);
    }
}
