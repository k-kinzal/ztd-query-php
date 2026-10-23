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

    public function testOfRetainsLegacyPluginNamesInsteadOfModernReplacements(): void
    {
        $legacy = array_column(ServerCatalog::of('5.6.51')->plugins, 0);
        $modern = array_column(ServerCatalog::of('8.4.7')->plugins, 0);

        self::assertContains('mysql_old_password', $legacy);
        self::assertContains('INNODB_SYS_TABLES', $legacy);
        self::assertNotContains('caching_sha2_password', $legacy);
        self::assertNotContains('TempTable', $legacy);
        self::assertNotContains('mysql_old_password', $modern);
        self::assertContains('caching_sha2_password', $modern);
    }

    public function testOfKeepsOnlyLegacyStaticPrivileges(): void
    {
        $privileges = ServerCatalog::of('5.6.51')->privileges;

        self::assertCount(31, $privileges);
        self::assertContains(['Super', 'Server Admin', 'To use KILL thread, SET GLOBAL, CHANGE MASTER, etc.'], $privileges);
        self::assertNotContains('Create role', array_column($privileges, 0));
        self::assertNotContains('SYSTEM_USER', array_column($privileges, 0));
    }
    public function testSortLengthFollowsTheCollationWeightingAlgorithm(): void
    {
        self::assertSame([0, 1, 2, 4, 8, 24], array_map(static fn (string $name): int => ServerCatalog::sortLength(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known($name)), ['utf8mb4_0900_ai_ci', 'utf8mb4_0900_bin', 'latin1_german2_ci', 'latin2_czech_cs', 'utf8mb4_unicode_520_ci', 'utf8mb4_ja_0900_as_cs_ks']));
    }

}
