<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\ServerCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerCommands::class)]
#[Medium]
final class ServerCommandsTest extends TestCase
{
    public function testBindRoutesAServerCommandToItsBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
    }

    public function testBindLeavesOtherStatementsToTheirFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO 1');
        self::assertSame('DO', $statement->kind->value);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindRoutesEachServerCommandAcrossReleases(): array
    {
        return [
            [Dialect::MySql, 'mysql-5.7.44', 'INSTALL PLUGIN p SONAME \'p.so\'', [\SqlSemantics\Model\Statement\Server\InstallPluginStatement::class, 'INSTALL PLUGIN `p` SONAME \'p.so\'']],
            [Dialect::MySql, 'mysql-5.7.44', 'UNINSTALL PLUGIN p', [\SqlSemantics\Model\Statement\Server\UninstallPluginStatement::class, 'UNINSTALL PLUGIN `p`']],
            [Dialect::MySql, null, 'INSTALL PLUGIN p SONAME \'p.so\'', [\SqlSemantics\Model\Statement\Server\InstallPluginStatement::class, 'INSTALL PLUGIN `p` SONAME \'p.so\'']],
            [Dialect::MySql, null, 'UNINSTALL PLUGIN p', [\SqlSemantics\Model\Statement\Server\UninstallPluginStatement::class, 'UNINSTALL PLUGIN `p`']],
            [Dialect::MySql, null, 'INSTALL COMPONENT \'file://a\'', [\SqlSemantics\Model\Statement\Server\Administration\InstallComponentStatement::class, 'INSTALL COMPONENT \'file://a\'']],
            [Dialect::MySql, null, 'UNINSTALL COMPONENT \'file://a\'', [\SqlSemantics\Model\Statement\Server\Administration\UninstallComponentStatement::class, 'UNINSTALL COMPONENT \'file://a\'']],
            [Dialect::MySql, null, 'ALTER INSTANCE RELOAD TLS', [\SqlSemantics\Model\Statement\Server\Administration\ReloadTlsStatement::class, 'ALTER INSTANCE RELOAD TLS']],
            [Dialect::MySql, null, 'CLONE LOCAL DATA DIRECTORY = \'/d\'', [\SqlSemantics\Model\Statement\Server\CloneLocalStatement::class, 'CLONE LOCAL DATA DIRECTORY \'/d\'']],
            [Dialect::MySql, 'mysql-5.7.44', 'CHANGE MASTER TO MASTER_HOST = \'h\'', [\SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement::class, 'CHANGE MASTER TO MASTER_HOST = \'h\'']],
            [Dialect::MySql, 'mysql-8.0.44', 'CHANGE MASTER TO MASTER_HOST = \'h\'', [\SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement::class, 'CHANGE REPLICATION SOURCE TO SOURCE_HOST = \'h\'']],
            [Dialect::MySql, null, 'CHANGE REPLICATION SOURCE TO SOURCE_HOST = \'h\'', [\SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement::class, 'CHANGE REPLICATION SOURCE TO SOURCE_HOST = \'h\'']],
            [Dialect::MySql, null, 'FLUSH LOGS', [\SqlSemantics\Model\Statement\Server\Administration\FlushServerStatement::class, 'FLUSH LOGS']],
            [Dialect::MySql, 'mysql-5.7.44', 'START SLAVE', [\SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement::class, 'START SLAVE']],
            [Dialect::MySql, 'mysql-5.7.44', 'STOP SLAVE', [\SqlSemantics\Model\Statement\Server\Replication\StopReplicaStatement::class, 'STOP SLAVE']],
            [Dialect::MySql, 'mysql-8.0.44', 'START SLAVE', [\SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement::class, 'START REPLICA']],
            [Dialect::MySql, 'mysql-8.0.44', 'STOP SLAVE', [\SqlSemantics\Model\Statement\Server\Replication\StopReplicaStatement::class, 'STOP REPLICA']],
            [Dialect::MySql, null, 'START REPLICA', [\SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement::class, 'START REPLICA']],
            [Dialect::MySql, null, 'STOP REPLICA', [\SqlSemantics\Model\Statement\Server\Replication\StopReplicaStatement::class, 'STOP REPLICA']],
            [Dialect::MySql, 'mysql-5.7.44', 'START GROUP_REPLICATION', [\SqlSemantics\Model\Statement\Server\Replication\StartGroupReplicationStatement::class, 'START GROUP_REPLICATION']],
            [Dialect::MySql, 'mysql-5.7.44', 'STOP GROUP_REPLICATION', [\SqlSemantics\Model\Statement\Server\Replication\StopGroupReplicationStatement::class, 'STOP GROUP_REPLICATION']],
            [Dialect::MySql, null, 'START GROUP_REPLICATION', [\SqlSemantics\Model\Statement\Server\Replication\StartGroupReplicationStatement::class, 'START GROUP_REPLICATION']],
            [Dialect::MySql, null, 'STOP GROUP_REPLICATION', [\SqlSemantics\Model\Statement\Server\Replication\StopGroupReplicationStatement::class, 'STOP GROUP_REPLICATION']],
            [Dialect::MySql, 'mysql-5.6.51', 'START SLAVE', [\SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement::class, 'START SLAVE']],
        ];
    }

    #[DataProvider('providerBindRoutesEachServerCommandAcrossReleases')]
    public function testBindRoutesEachServerCommandAcrossReleases(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }
}
