<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\Grammar\SqlVersion;

#[CoversClass(SqlVersionRegistry::class)]
#[UsesClass(SqlVersion::class)]
final class SqlVersionRegistryTest extends TestCase
{
    public function testResolveAnswersTheDefaultReleaseOfADialect(): void
    {
        $version = (new SqlVersionRegistry())->resolve('mysql');

        self::assertSame('mysql', $version->dialect);
        self::assertSame('mysql-8.4.7', $version->name);
        self::assertFileExists($version->astPath);
        self::assertFileExists($version->lexicalPath);
    }

    public function testResolveRejectsADialectThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect: oracle');

        (new SqlVersionRegistry())->resolve('oracle');
    }

    public function testResolveRejectsAReleaseThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mysql version: mysql-999.0.0');

        (new SqlVersionRegistry())->resolve('mysql', 'mysql-999.0.0');
    }

    public function testNamesEnumeratesTheReleasesOfOneDialect(): void
    {
        self::assertSame(
            [
                'mysql-5.6.51',
                'mysql-5.7.44',
                'mysql-8.0.44',
                'mysql-8.1.0',
                'mysql-8.2.0',
                'mysql-8.3.0',
                'mysql-8.4.7',
                'mysql-9.0.1',
                'mysql-9.1.0',
            ],
            (new SqlVersionRegistry())->names('mysql'),
        );
    }

    public function testNamesRejectsADialectThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect: oracle');

        (new SqlVersionRegistry())->names('oracle');
    }

    public function testAllEnumeratesEveryReleaseOfEveryDialect(): void
    {
        self::assertCount(11, (new SqlVersionRegistry())->all());
    }

    public function testEntriesReadsTheRecordOfGeneratedReleases(): void
    {
        self::assertSame(['mysql', 'postgresql', 'sqlite'], array_keys((new SqlVersionRegistry())->entries()));
    }

    public function testPathResolvesARecordedPathAgainstTheResourceDirectory(): void
    {
        $registry = new SqlVersionRegistry();

        self::assertSame($registry->directory() . '/mysql/ast.php', $registry->path('mysql/ast.php'));
    }

    public function testPathRejectsAPathThatClimbsOutOfTheResourceDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid SQL version resource path: ../composer.json');

        (new SqlVersionRegistry())->path('../composer.json');
    }

    public function testDirectoryAnswersWhereGeneratedArtifactsAreCommitted(): void
    {
        self::assertDirectoryExists((new SqlVersionRegistry())->directory());
    }
}
