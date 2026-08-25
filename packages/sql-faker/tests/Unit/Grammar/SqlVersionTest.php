<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\SqlVersionRegistry;

#[CoversClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
final class SqlVersionTest extends TestCase
{
    public function testResolvesTheDefaultVersionWithBothArtifacts(): void
    {
        $version = SqlVersion::resolve('mysql');

        self::assertSame('mysql', $version->dialect);
        self::assertSame('mysql-8.4.7', $version->name);
        self::assertTrue(is_file($version->astPath));
        self::assertTrue(is_file($version->lexicalPath));
    }

    public function testEnumeratesEveryRegisteredArtifactPair(): void
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

    public function testEnumeratesNamesForOneDialect(): void
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

    public function testRejectsNamesForAnUnknownDialect(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect: unknown');

        SqlVersion::names('unknown');
    }

    public function testRejectsAnUnsupportedVersion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mysql version');

        SqlVersion::resolve('mysql', 'mysql-999.0.0');
    }

    public function testRejectsAnUnknownDialect(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown SQL dialect');

        SqlVersion::resolve('oracle');
    }

    public function testConstructBindsTheArtifactsToThePathsTheCallerChose(): void
    {
        $version = new SqlVersion('mysql', 'mysql-8.4.7', '/tmp/ast.php', '/tmp/lex.php');

        self::assertSame('mysql', $version->dialect);
        self::assertSame('mysql-8.4.7', $version->name);
        self::assertSame('/tmp/ast.php', $version->astPath);
        self::assertSame('/tmp/lex.php', $version->lexicalPath);
    }
}
