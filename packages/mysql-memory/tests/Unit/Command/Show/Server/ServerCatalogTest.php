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
}
