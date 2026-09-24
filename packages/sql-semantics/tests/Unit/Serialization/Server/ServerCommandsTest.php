<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\ServerCommands;

#[CoversClass(ServerCommands::class)]
#[Medium]
final class ServerCommandsTest extends TestCase
{
    public function testWriteRoutesOnlyServerCommands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertNotNull(ServerCommands::write($binder->bind("PURGE BINARY LOGS TO 'a'")));
        self::assertNull(ServerCommands::write($binder->bind('DO 1')));
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerWriteSpellsEachServerCommand(): array
    {
        return [
            [Dialect::MySql, null, 'PURGE BINARY LOGS BEFORE \'2020-01-01\'', 'PURGE BINARY LOGS BEFORE \'2020-01-01\''],
            [Dialect::MySql, null, 'INSTALL COMPONENT \'file://a\'', 'INSTALL COMPONENT \'file://a\''],
            [Dialect::MySql, null, 'UNINSTALL COMPONENT \'file://a\'', 'UNINSTALL COMPONENT \'file://a\''],
            [Dialect::MySql, null, 'ALTER INSTANCE ROTATE INNODB MASTER KEY', 'ALTER INSTANCE ROTATE INNODB MASTER KEY'],
            [Dialect::MySql, null, 'ALTER INSTANCE RELOAD TLS', 'ALTER INSTANCE RELOAD TLS'],
            [Dialect::MySql, null, 'ALTER INSTANCE RELOAD KEYRING', 'ALTER INSTANCE RELOAD KEYRING'],
            [Dialect::MySql, null, 'ALTER INSTANCE DISABLE INNODB REDO_LOG', 'ALTER INSTANCE DISABLE INNODB REDO_LOG'],
            [Dialect::MySql, null, 'CHANGE REPLICATION SOURCE TO SOURCE_HOST = \'h\'', 'CHANGE REPLICATION SOURCE TO SOURCE_HOST = \'h\''],
            [Dialect::MySql, null, 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (d)', 'CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`d`)'],
            [Dialect::MySql, null, 'FLUSH TABLES', 'FLUSH TABLES'],
            [Dialect::MySql, null, 'FLUSH TABLES WITH READ LOCK', 'FLUSH TABLES WITH READ LOCK'],
            [Dialect::MySql, null, 'FLUSH TABLES t FOR EXPORT', 'FLUSH TABLES `t` FOR EXPORT'],
            [Dialect::MySql, null, 'FLUSH PRIVILEGES', 'FLUSH PRIVILEGES'],
            [Dialect::MySql, null, 'RESET BINARY LOGS AND GTIDS', 'RESET BINARY LOGS AND GTIDS'],
            [Dialect::MySql, null, 'START REPLICA', 'START REPLICA'],
            [Dialect::MySql, null, 'STOP REPLICA', 'STOP REPLICA'],
            [Dialect::MySql, null, 'START GROUP_REPLICATION', 'START GROUP_REPLICATION'],
            [Dialect::MySql, null, 'STOP GROUP_REPLICATION', 'STOP GROUP_REPLICATION'],
        ];
    }

    #[DataProvider('providerWriteSpellsEachServerCommand')]
    public function testWriteSpellsEachServerCommand(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertSame($expected, ServerCommands::write($statement)?->toString());
    }
}
