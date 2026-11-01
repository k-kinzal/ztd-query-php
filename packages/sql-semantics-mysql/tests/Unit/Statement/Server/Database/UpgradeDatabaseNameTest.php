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

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRetainedNames(): iterable
    {
        yield 'ordinary' => ['ordinary'];
        yield 'empty suffix' => ['#mysql50#'];
        yield 'separator' => ['#mysql50#a/b'];
        yield 'period' => ['#mysql50#a.b'];
        yield 'long suffix' => ['#mysql50#' . str_repeat('a', 65)];
    }

    #[DataProvider('providerRetainedNames')]
    public function testConditionKeepsExecutionRefusalsInAReconstructableStatement(string $name): void
    {
        $sql = 'ALTER DATABASE `' . $name . '` UPGRADE DATA DIRECTORY NAME';
        $statement = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze($sql);
        self::assertInstanceOf(UpgradeDatabaseName::class, $statement);
        self::assertSame($sql, $statement->toString());
        self::assertNotNull($statement->condition());
        self::assertSame($name, $statement->condition()->name->value);
        self::assertSame([], $statement->diagnostics());
    }

    public function testIdentifierConvertsRawNamesWithoutChangingTheOperand(): void
    {
        $statement = new UpgradeDatabaseName(new \SqlSemantics\Statement\Identifier\Name('#mysql50#🦄', \SqlSemantics\Statement\Identifier\NameQuote::Backtick));
        self::assertSame('#mysql50#?', $statement->identifier()->value);
        self::assertNull($statement->condition());
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $statement->name);
        self::assertSame('#mysql50#🦄', $statement->name->value);
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
