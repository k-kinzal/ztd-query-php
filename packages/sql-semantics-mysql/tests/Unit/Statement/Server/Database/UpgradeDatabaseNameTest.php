<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;

#[CoversClass(UpgradeDatabaseName::class)]
#[Medium]
final class UpgradeDatabaseNameTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('ALTER DATABASE `#mysql50#x` UPGRADE DATA DIRECTORY NAME', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter database `#mysql50#x` upgrade data directory name')->toString());
    }

    public function testDeriveStatementHasNoRelationalFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER DATABASE `#mysql50#d` UPGRADE DATA DIRECTORY NAME');

        self::assertSame([], $operation->diagnostics());
        self::assertNull($operation->output());
    }

    /**
     * @return iterable<string, array{string, list<Deprecation>}>
     */
    public static function providerWarnings(): iterable
    {
        yield '5.6' => ['mysql-5.6.51', []];
        yield '5.7' => ['mysql-5.7.44', [new Deprecation(Deprecated::UpgradeDatabaseName)]];
    }

    /**
     * @param list<Deprecation> $warnings The native deprecation facts
     */
    #[DataProvider('providerWarnings')]
    public function testDeriveStatementWarnsOnlyInMySql57(string $release, array $warnings): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('ALTER DATABASE `#mysql50#d` UPGRADE DATA DIRECTORY NAME');

        self::assertEquals($warnings, $operation->warnings());
    }
}
