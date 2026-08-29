<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\SqlVersionRegistry;

#[CoversClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
final class SqlVersionTest extends TestCase
{
    public function testResolveAnswersTheDefaultReleaseWithBothArtifacts(): void
    {
        $version = SqlVersion::resolve('mysql');

        self::assertSame('mysql', $version->dialect);
        self::assertSame('mysql-8.4.7', $version->name);
        self::assertTrue(is_file($version->astPath));
        self::assertTrue(is_file($version->lexicalPath));
    }

    public function testAllEnumeratesEveryRegisteredArtifactPair(): void
    {
        $versions = SqlVersion::all();

        self::assertCount(11, $versions);
        self::assertSame(
            ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44'],
            array_slice(array_map(static fn (SqlVersion $version): string => $version->name, $versions), 0, 3),
        );
        array_walk($versions, static function (SqlVersion $version): void {
            self::assertTrue(is_file($version->astPath));
            self::assertTrue(is_file($version->lexicalPath));
        });
    }

    public function testNamesEnumeratesTheReleasesOfOneDialect(): void
    {
        self::assertSame([
            'mysql-5.6.51',
            'mysql-5.7.44',
            'mysql-8.0.44',
            'mysql-8.1.0',
            'mysql-8.2.0',
            'mysql-8.3.0',
            'mysql-8.4.7',
            'mysql-9.0.1',
            'mysql-9.1.0',
        ], SqlVersion::names('mysql'));
    }

    public function testNamesRejectsADialectThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect: unknown');

        SqlVersion::names('unknown');
    }

    public function testResolveRejectsAReleaseThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mysql version');

        SqlVersion::resolve('mysql', 'mysql-999.0.0');
    }

    public function testResolveRejectsADialectThePackageDoesNotShip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect');

        SqlVersion::resolve('oracle');
    }

}
