<?php

declare(strict_types=1);

namespace Tests\Unit\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;
use SqlParser\Sqlite\SqliteVersion;

#[CoversClass(SqliteVersion::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(VersionRegistry::class)]
#[Small]
final class SqliteVersionTest extends TestCase
{
    public function testResolve(): void
    {
        self::assertSame('sqlite-3.47.2', SqliteVersion::resolve()->name());
        self::assertSame('sqlite-3.47.2', SqliteVersion::resolve('sqlite-3.47.2')->release->name);
    }

    public function testResolveRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);

        SqliteVersion::resolve('sqlite-2.8.17');
    }

    public function testName(): void
    {
        self::assertSame('sqlite-3.47.2', SqliteVersion::resolve('sqlite-3.47.2')->name());
    }
}
