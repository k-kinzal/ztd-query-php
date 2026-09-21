<?php

declare(strict_types=1);

namespace Tests\Unit\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Resource\SqlVersion;

#[CoversClass(SqlVersion::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class SqlVersionTest extends TestCase
{
    public function testPropertiesAreKept(): void
    {
        $version = new SqlVersion('mysql', 'mysql-8.4.7', '/tables/a.bin', '/keywords/a.php');

        self::assertSame('mysql', $version->dialect);
        self::assertSame('mysql-8.4.7', $version->name);
        self::assertSame('/tables/a.bin', $version->tablePath);
        self::assertSame('/keywords/a.php', $version->keywordPath);
    }
}
