<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Closure;
use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\Reset;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ResetVocabularyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        yield '8.2' => ['8.2.0'];
        yield '8.3' => ['8.3.0'];
    }

    #[DataProvider('providerReleases')]
    public function testDirectTargetsRetainSynonymsAndTheirOrderedNotices(string $release): void
    {
        $targets = [static fn () => new ResetBinaryLogs(Terminology::Current, synonym: true), static fn () => new ResetBinaryLogs(Terminology::Current), static fn () => new ResetReplica(Terminology::Current, synonym: true), static fn () => new ResetReplica(Terminology::Current)];
        $nodes = array_merge(...array_map(static fn (Closure $first): array => array_map(static fn (Closure $second): Reset => new Reset([$first(), $second()]), $targets), $targets));
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $expected = array_map(static fn (Reset $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()))), $nodes);
                $session->text = 'unrelated invalid input';
                $actual = array_map(static fn (Reset $node): string => serialize((new Memory())->observe($session, $node)), $nodes);

                self::assertSame($expected, $actual);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }

    #[DataProvider('providerReleases')]
    public function testInputKeepsEarlierVocabularyNoticesWhenLaterTargetsFail(string $release): void
    {
        $sqls = [
            'RESET SLAVE, MASTER', 'RESET SLAVE, BINARY LOGS AND GTIDS',
            'RESET MASTER, BINARY LOGS AND GTIDS, REPLICA',
            'RESET SLAVE, REPLICA, MASTER',
            'RESET SLAVE, BINARY LOGS AND GTIDS TO 0',
            'RESET MASTER, REPLICA FOR CHANNEL 0x41',
            'RESET MASTER, BINARY LOGS AND GTIDS TO 0',
            'RESET SLAVE, BINARY LOGS AND GTIDS TO',
            'CREATE DATABASE d', 'USE d',
            'CREATE PROCEDURE p() BEGIN RESET SLAVE; RESET BINARY LOGS AND GTIDS TO 0; END',
        ];
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $sqls);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $sqls);

                self::assertSame($expected, $actual);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
