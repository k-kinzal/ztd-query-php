<?php

declare(strict_types=1);

namespace Tests\Unit\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\MySql\MySqlVersion;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

#[CoversClass(MySqlVersion::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(VersionRegistry::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class MySqlVersionTest extends TestCase
{
    public function testResolve(): void
    {
        self::assertSame('mysql-8.4.7', MySqlVersion::resolve()->name());
        self::assertSame('mysql-5.6.51', MySqlVersion::resolve('mysql-5.6.51')->name());
    }

    public function testResolveRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);

        MySqlVersion::resolve('mysql-4.1.0');
    }

    public function testName(): void
    {
        self::assertSame('mysql-9.1.0', MySqlVersion::resolve('mysql-9.1.0')->name());
    }

    public function testId(): void
    {
        self::assertSame(80407, MySqlVersion::resolve('mysql-8.4.7')->id());
        self::assertSame(50651, MySqlVersion::resolve('mysql-5.6.51')->id());
        self::assertSame(90100, MySqlVersion::resolve('mysql-9.1.0')->id());
    }

    public function testHasJsonOperators(): void
    {
        self::assertFalse(MySqlVersion::resolve('mysql-5.6.51')->hasJsonOperators());
        self::assertTrue(MySqlVersion::resolve('mysql-5.7.44')->hasJsonOperators());
    }

    public function testHasDollarQuotedStrings(): void
    {
        self::assertFalse(MySqlVersion::resolve('mysql-8.0.44')->hasDollarQuotedStrings());
        self::assertTrue(MySqlVersion::resolve('mysql-8.1.0')->hasDollarQuotedStrings());
    }

    public function testMergesWithCube(): void
    {
        self::assertTrue(MySqlVersion::resolve('mysql-5.7.44')->mergesWithCube());
        self::assertFalse(MySqlVersion::resolve('mysql-8.0.44')->mergesWithCube());
    }
}
