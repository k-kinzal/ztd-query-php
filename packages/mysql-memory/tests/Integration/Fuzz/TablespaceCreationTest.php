<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TablespaceCreationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d', 'CREATE TABLESPACE ts0_0 ADD DATAFILE \'\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_1 ADD DATAFILE \'a\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_2 ADD DATAFILE \'ab\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_3 ADD DATAFILE \'abc\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_4 ADD DATAFILE \'abcd\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_5 ADD DATAFILE \'abcde\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_6 ADD DATAFILE \'abcdef\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_7 ADD DATAFILE \'.ibd\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_8 ADD DATAFILE \'a.ibd\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_9 ADD DATAFILE \'ab.ibd\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_10 ADD DATAFILE \'text\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_11 ADD DATAFILE \'texts\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_12 ADD DATAFILE \'bad.x\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_13 ADD DATAFILE \'folder/file\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_14 ADD DATAFILE \'あ\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts0_15 ADD DATAFILE \'ああ\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE ts1_0 ADD DATAFILE \'\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_1 ADD DATAFILE \'a\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_2 ADD DATAFILE \'ab\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_3 ADD DATAFILE \'abc\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_4 ADD DATAFILE \'abcd\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_5 ADD DATAFILE \'abcde\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_6 ADD DATAFILE \'abcdef\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_7 ADD DATAFILE \'.ibd\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_8 ADD DATAFILE \'a.ibd\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_9 ADD DATAFILE \'ab.ibd\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_10 ADD DATAFILE \'text\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_11 ADD DATAFILE \'texts\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_12 ADD DATAFILE \'bad.x\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_13 ADD DATAFILE \'folder/file\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_14 ADD DATAFILE \'あ\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts1_15 ADD DATAFILE \'ああ\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE ts32768_0 ADD DATAFILE \'\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_1 ADD DATAFILE \'a\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_2 ADD DATAFILE \'ab\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_3 ADD DATAFILE \'abc\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_4 ADD DATAFILE \'abcd\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_5 ADD DATAFILE \'abcde\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_6 ADD DATAFILE \'abcdef\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_7 ADD DATAFILE \'.ibd\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_8 ADD DATAFILE \'a.ibd\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_9 ADD DATAFILE \'ab.ibd\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_10 ADD DATAFILE \'text\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_11 ADD DATAFILE \'texts\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_12 ADD DATAFILE \'bad.x\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_13 ADD DATAFILE \'folder/file\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_14 ADD DATAFILE \'あ\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE ts32768_15 ADD DATAFILE \'ああ\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE s0bad ADD DATAFILE \'s0bad\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE s0good ADD DATAFILE \'s0good.ibd\' FILE_BLOCK_SIZE 0', 'CREATE TABLESPACE s1bad ADD DATAFILE \'s1bad\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE s1good ADD DATAFILE \'s1good.ibd\' FILE_BLOCK_SIZE 1', 'CREATE TABLESPACE s512bad ADD DATAFILE \'s512bad\' FILE_BLOCK_SIZE 512', 'CREATE TABLESPACE s512good ADD DATAFILE \'s512good.ibd\' FILE_BLOCK_SIZE 512', 'CREATE TABLESPACE s1024bad ADD DATAFILE \'s1024bad\' FILE_BLOCK_SIZE 1024', 'CREATE TABLESPACE s1024good ADD DATAFILE \'s1024good.ibd\' FILE_BLOCK_SIZE 1024', 'CREATE TABLESPACE s2048bad ADD DATAFILE \'s2048bad\' FILE_BLOCK_SIZE 2048', 'CREATE TABLESPACE s2048good ADD DATAFILE \'s2048good.ibd\' FILE_BLOCK_SIZE 2048', 'CREATE TABLESPACE s4096bad ADD DATAFILE \'s4096bad\' FILE_BLOCK_SIZE 4096', 'CREATE TABLESPACE s4096good ADD DATAFILE \'s4096good.ibd\' FILE_BLOCK_SIZE 4096', 'CREATE TABLESPACE s8192bad ADD DATAFILE \'s8192bad\' FILE_BLOCK_SIZE 8192', 'CREATE TABLESPACE s8192good ADD DATAFILE \'s8192good.ibd\' FILE_BLOCK_SIZE 8192', 'CREATE TABLESPACE s16384bad ADD DATAFILE \'s16384bad\' FILE_BLOCK_SIZE 16384', 'CREATE TABLESPACE s16384good ADD DATAFILE \'s16384good.ibd\' FILE_BLOCK_SIZE 16384', 'CREATE TABLESPACE s32768bad ADD DATAFILE \'s32768bad\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE s32768good ADD DATAFILE \'s32768good.ibd\' FILE_BLOCK_SIZE 32768', 'CREATE TABLESPACE s65536bad ADD DATAFILE \'s65536bad\' FILE_BLOCK_SIZE 65536', 'CREATE TABLESPACE s65536good ADD DATAFILE \'s65536good.ibd\' FILE_BLOCK_SIZE 65536'];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerSql')]
    public function testBlockSizesAndFilenamesRetainEveryOrderedCondition(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);
                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('tablespace-creation-state-20261017');
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 8; $trial++) {
                $name = 's' . $trial . '_' . $choices->integer(0, 1000);
                $size = new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral($choices->one(['0', '1024', '2048', '4096', '8192', '16384'])));
                $structures[] = new \SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace(new Name($name), new \SqlSemantics\Platform\MySql\Statement\Literal\Text($name . '.ibd'), options: [new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption(\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind::FileBlock, $size)]);
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace> $structures
     */
    #[DataProvider('providerStructures')]
    public function testUnchangedDefinitionsRetainCreationAndRemovalHistories(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $observations = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace $definition) use ($native, $session): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($definition, $session->semantics()->profile());
                $history = array_map(static function (string $change) use ($native, $session, $definition, $sql): array {
                    $native->query($change);
                    $session->query($change);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $definition)];
                }, ['SET @unrelated=1', 'SET @unrelated=2', 'DROP TABLESPACE ' . $definition->name->value]);
                self::assertSame([null, 1813, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return $history;
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
