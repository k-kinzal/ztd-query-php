<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Baseline;
use Fuzz\Target\Differential;
use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class ReplicationTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSettings(): iterable
    {
        foreach ([
            'SOURCE_HEARTBEAT_PERIOD=0',
            'SOURCE_HEARTBEAT_PERIOD=0.0001',
            'SOURCE_HEARTBEAT_PERIOD=60',
            'SOURCE_HEARTBEAT_PERIOD=100',
            "SOURCE_USER='u'",
            "SOURCE_PASSWORD=''",
            "SOURCE_SSL=1,SOURCE_USER='u'",
            "SOURCE_LOG_FILE='f'",
            "SOURCE_LOG_FILE='f',SOURCE_LOG_POS=4",
            "SOURCE_COMPRESSION_ALGORITHMS='zlib,zstd'",
            "SOURCE_COMPRESSION_ALGORITHMS='ZLIB'",
            "SOURCE_COMPRESSION_ALGORITHMS='zlib,'",
            "SOURCE_COMPRESSION_ALGORITHMS='zlib, zstd'",
            "SOURCE_COMPRESSION_ALGORITHMS='zlib,,zstd'",
            "SOURCE_COMPRESSION_ALGORITHMS=''",
            'SOURCE_AUTO_POSITION=1',
            "RELAY_LOG_FILE='missing'",
            "RELAY_LOG_FILE=''",
            "RELAY_LOG_FILE='missing',SOURCE_USER='u'",
            "RELAY_LOG_FILE='missing',SOURCE_COMPRESSION_ALGORITHMS='bad'",
            "RELAY_LOG_FILE='missing',SOURCE_AUTO_POSITION=1",
            'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=LOCAL',
            'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS=OFF',
            'PRIVILEGE_CHECKS_USER=missing',
            "PRIVILEGE_CHECKS_USER='root'@'%'",
            "SOURCE_USER='u',SOURCE_LOG_FILE='f',SOURCE_HEARTBEAT_PERIOD=100,SOURCE_COMPRESSION_ALGORITHMS='bad'",
            "SOURCE_AUTO_POSITION=1,SOURCE_USER='u',SOURCE_HEARTBEAT_PERIOD=100",
            "SOURCE_COMPRESSION_ALGORITHMS='bad',PRIVILEGE_CHECKS_USER=missing",
        ] as $options) {
            yield $options => ['CHANGE REPLICATION SOURCE TO ' . $options];
        }
        yield 'uninitialized applier' => ['RESET REPLICA ALL; START REPLICA SQL_THREAD'];
        yield 'configured applier' => ['CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; START REPLICA SQL_THREAD; STOP REPLICA'];
        yield 'reset applier' => ['CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; RESET REPLICA; START REPLICA SQL_THREAD'];
        yield 'uninitialized relay log' => ['RESET REPLICA ALL; SHOW RELAYLOG EVENTS'];
        yield 'uninitialized relay file' => ["RESET REPLICA ALL; SHOW RELAYLOG EVENTS IN 'missing' FROM 18446744073709551615"];
        yield 'initialized relay log' => ['CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; SHOW RELAYLOG EVENTS'];
        yield 'reset relay log' => ['CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; RESET REPLICA ALL; SHOW RELAYLOG EVENTS'];
        yield 'preserved relay log' => ['CHANGE REPLICATION SOURCE TO SOURCE_HEARTBEAT_PERIOD=0; RESET REPLICA ALL; CHANGE REPLICATION SOURCE TO RELAY_LOG_POS=4; SHOW RELAYLOG EVENTS'];
        yield 'missing group user' => ["START GROUP_REPLICATION PASSWORD='p'"];
        yield 'empty group user' => ["START GROUP_REPLICATION USER='',PASSWORD='p'"];
        yield 'group credentials before transaction' => ["BEGIN; START GROUP_REPLICATION PASSWORD='p'"];
        yield 'group default authentication alone' => ["START GROUP_REPLICATION DEFAULT_AUTH='x'"];
        yield 'last group user wins' => ["START GROUP_REPLICATION USER='u',USER='' "];
        yield 'replica user alone' => ["START REPLICA USER='u'"];
        yield 'replica empty user' => ["START REPLICA USER=''"];
        yield 'replica user and password' => ["START REPLICA USER='u' PASSWORD='p'"];
    }

    #[DataProvider('providerSettings')]
    public function testSettingsMatchTheServer(string $sql): void
    {
        [$base] = Servers::shared();
        $native = new PDO($base->native, $base->nativeUser, $base->nativePassword);
        $target = new Differential($base->native, $base->nativeUser, $base->nativePassword, $base->memory, version: $base->version, guardUser: $base->guardUser, baseline: new Baseline($native, $base->version));
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, $sql . "\n" . $comparison->referenceDifference);
        self::assertNull($comparison->difference, $sql . "\n" . $comparison->difference);
    }
}
