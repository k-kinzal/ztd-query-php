<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerCatalog::class)]
#[Small]
final class ServerCatalogTest extends TestCase
{
    public function testSharedReadsTheCatalogOnce(): void
    {
        self::assertSame(ServerCatalog::shared(), ServerCatalog::shared());
        self::assertCount(11, ServerCatalog::shared()->engines);
        self::assertSame('UTF-8 Unicode', ServerCatalog::shared()->charsets['utf8mb4']);
        self::assertSame(8, ServerCatalog::shared()->collations['utf8mb4_unicode_ci']);
    }

    public function testEngineNamesAnEnabledEngineByNameOrAlias(): void
    {
        self::assertSame(['InnoDB', 'InnoDB', 'MEMORY', 'MRG_MYISAM', null, null], [
            ServerCatalog::shared()->engine('innodb'),
            ServerCatalog::shared()->engine('INNOBASE'),
            ServerCatalog::shared()->engine('heap'),
            ServerCatalog::shared()->engine('merge'),
            ServerCatalog::shared()->engine('FEDERATED'),
            ServerCatalog::shared()->engine('nosuch'),
        ]);
    }

    public function testOfAnswersThePluginsAndPrivilegesOfARelease(): void
    {
        $native = static fn (ServerCatalog $catalog): array => array_values(array_filter($catalog->plugins, static fn (array $plugin): bool => $plugin[0] === 'mysql_native_password'));

        self::assertSame([['mysql_native_password', 'ACTIVE', 'AUTHENTICATION', null, 'GPL']], $native(ServerCatalog::of('8.0.44')));
        self::assertSame('mysql_native_password', ServerCatalog::of('8.0.44')->plugins[1][0]);
        self::assertSame([['mysql_native_password', 'DISABLED', 'AUTHENTICATION', null, 'GPL']], $native(ServerCatalog::of('8.4.7')));
        self::assertSame([], $native(ServerCatalog::of('9.1.0')));
        self::assertContains(['SET_USER_ID', 'Server Admin', ''], ServerCatalog::of('8.0.44')->privileges);
        self::assertNotContains(['SET_USER_ID', 'Server Admin', ''], ServerCatalog::of('8.4.7')->privileges);
    }

    public function testOfKeepsOnlyLegacyStaticPrivileges(): void
    {
        $privileges = ServerCatalog::of('5.6.51')->privileges;

        self::assertCount(31, $privileges);
        self::assertContains(['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'], $privileges);
        self::assertNotContains('Create role', array_column($privileges, 0));
        self::assertNotContains('SYSTEM_USER', array_column($privileges, 0));
    }
}
