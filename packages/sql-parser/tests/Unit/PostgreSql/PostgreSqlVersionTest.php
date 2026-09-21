<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\PostgreSql\PostgreSqlVersion;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

#[CoversClass(PostgreSqlVersion::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(VersionRegistry::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class PostgreSqlVersionTest extends TestCase
{
    public function testResolve(): void
    {
        self::assertSame('pg-17.2', PostgreSqlVersion::resolve()->name());
        self::assertSame('pg-17.2', PostgreSqlVersion::resolve('pg-17.2')->release->name);
    }

    public function testResolveRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);

        PostgreSqlVersion::resolve('pg-9.6');
    }

    public function testName(): void
    {
        self::assertSame('pg-17.2', PostgreSqlVersion::resolve('pg-17.2')->name());
    }
}
