<?php

declare(strict_types=1);

namespace Tests\Unit\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Resource\SqlVersion;
use SqlParser\Resource\VersionRegistry;

#[CoversClass(VersionRegistry::class)]
#[UsesClass(SqlVersion::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class VersionRegistryTest extends TestCase
{
    public function testResolveDefaultsToTheNewestRelease(): void
    {
        $version = (new VersionRegistry())->resolve('mysql');

        self::assertSame('mysql-8.4.7', $version->name);
        self::assertStringEndsWith('/resources/tables/mysql-8.4.7.bin', $version->tablePath);
        self::assertStringEndsWith('/resources/keywords/mysql-8.4.7.php', $version->keywordPath);
        self::assertFileExists($version->tablePath);
    }

    public function testResolveAcceptsAnExplicitRelease(): void
    {
        self::assertSame('sqlite-3.47.2', (new VersionRegistry())->resolve('sqlite', 'sqlite-3.47.2')->name);
    }

    public function testResolveRejectsAnUnknownDialect(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect: oracle');

        (new VersionRegistry())->resolve('oracle');
    }

    public function testResolveRejectsAnUnknownRelease(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported postgresql version: pg-9.6');

        (new VersionRegistry())->resolve('postgresql', 'pg-9.6');
    }

    public function testNames(): void
    {
        $names = (new VersionRegistry())->names('mysql');

        self::assertSame('mysql-5.6.51', $names[0]);
        self::assertContains('mysql-9.1.0', $names);
        self::assertSame(['pg-17.2'], (new VersionRegistry())->names('postgresql'));
    }

    public function testNamesRejectsAnUnknownDialect(): void
    {
        $this->expectException(RuntimeException::class);

        (new VersionRegistry())->names('oracle');
    }

    public function testEntries(): void
    {
        $entries = (new VersionRegistry())->entries();

        self::assertSame(['mysql', 'postgresql', 'sqlite'], array_keys($entries));
        self::assertSame('tables/sqlite-3.47.2.bin', $entries['sqlite']['versions']['sqlite-3.47.2']['table']);
    }

    public function testPath(): void
    {
        self::assertSame('/res/tables/x.bin', (new VersionRegistry('/res'))->path('tables/x.bin'));
    }

    public function testPathRejectsAPathThatClimbsOut(): void
    {
        $this->expectException(RuntimeException::class);

        (new VersionRegistry('/res'))->path('../etc/passwd');
    }

    public function testPathRejectsAnAbsolutePath(): void
    {
        $this->expectException(RuntimeException::class);

        (new VersionRegistry('/res'))->path('/etc/passwd');
    }

    public function testDirectory(): void
    {
        self::assertSame('/res', (new VersionRegistry('/res'))->directory());
        self::assertStringEndsWith('/resources', (new VersionRegistry())->directory());
    }
}
