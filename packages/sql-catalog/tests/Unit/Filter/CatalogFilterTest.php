<?php

declare(strict_types=1);

namespace Tests\Unit\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Filter\CatalogFilter;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(CatalogFilter::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(Catalog::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Severity::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
final class CatalogFilterTest extends TestCase
{
    public function testIsEmptyWhenNoCriterionWasGiven(): void
    {
        self::assertTrue((new CatalogFilter())->isEmpty());
        self::assertFalse((new CatalogFilter(['App']))->isEmpty());
        self::assertFalse((new CatalogFilter(minimumSeverity: Severity::High))->isEmpty());
    }

    public function testApplyKeepsEverythingWhenTheFilterIsEmpty(): void
    {
        $catalog = new Catalog([new CatalogEntry(
            'a',
            StatementKind::Select,
            TextPattern::fromText('SELECT 1'),
            ['users'],
            [],
            new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'),
            [],
        )]);
        self::assertSame($catalog, (new CatalogFilter())->apply($catalog));
    }

    public function testApplyKeepsOnlyTheMatchingStatements(): void
    {
        $kept = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), []);
        $dropped = new CatalogEntry('b', StatementKind::Insert, TextPattern::fromText('INSERT INTO t VALUES (1)'), ['t'], [], new CallSite('src/b.php', 1, 'Other\\R::add', 'pdo.exec'), []);
        $filtered = (new CatalogFilter(kinds: [StatementKind::Select]))->apply(new Catalog([$kept, $dropped]));
        self::assertSame([$kept], $filtered->entries());
    }

    public function testMatchesNamespaceComparesThePrefixOfTheEnclosingFunction(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\Repository\\User::find', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(['App\\Repository']))->matchesNamespace($entry));
        self::assertTrue((new CatalogFilter(['App']))->matchesNamespace($entry));
        self::assertFalse((new CatalogFilter(['Other']))->matchesNamespace($entry));
        self::assertTrue((new CatalogFilter())->matchesNamespace($entry));
    }

    public function testMatchesFunctionAcceptsTheShortNameOrTheQualifiedOne(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\User::find', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(functions: ['find']))->matchesFunction($entry));
        self::assertTrue((new CatalogFilter(functions: ['App\\User::find']))->matchesFunction($entry));
        self::assertTrue((new CatalogFilter(functions: ['App\\User::*']))->matchesFunction($entry));
        self::assertFalse((new CatalogFilter(functions: ['save']))->matchesFunction($entry));
        self::assertTrue((new CatalogFilter())->matchesFunction($entry));
    }

    public function testMatchesFunctionHandlesAFreeFunction(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'App\\find', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(functions: ['App\\find']))->matchesFunction($entry));
    }

    public function testMatchesPathAcceptsAPatternOrADirectory(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/Repository/User.php', 1, 'f', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(paths: ['src/Repository/*.php']))->matchesPath($entry));
        self::assertTrue((new CatalogFilter(paths: ['src']))->matchesPath($entry));
        self::assertFalse((new CatalogFilter(paths: ['tests']))->matchesPath($entry));
        self::assertTrue((new CatalogFilter())->matchesPath($entry));
    }

    public function testMatchesNamespaceIgnoresALeadingBackslashOnEitherSide(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, '\\App\\User::find', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(['\\App']))->matchesNamespace($entry));
        self::assertTrue((new CatalogFilter(['App\\']))->matchesNamespace($entry));
    }

    public function testMatchesFunctionIgnoresALeadingBackslashOnEitherSide(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, '\\App\\User::find', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(functions: ['\\App\\User::find']))->matchesFunction($entry));
        self::assertTrue((new CatalogFilter(functions: ['find']))->matchesFunction($entry));
    }

    public function testMatchesPathAcceptsADirectoryWrittenWithATrailingSlash(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('src/Repository/User.php', 1, 'f', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(paths: ['src/']))->matchesPath($entry));
        self::assertFalse((new CatalogFilter(paths: ['src/Other/']))->matchesPath($entry));
    }

    public function testMatchesKindComparesTheStatementKind(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(kinds: [StatementKind::Select]))->matchesKind($entry));
        self::assertFalse((new CatalogFilter(kinds: [StatementKind::Insert]))->matchesKind($entry));
    }

    public function testMatchesTableIgnoresCase(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['Users'], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(tables: ['users']))->matchesTable($entry));
        self::assertFalse((new CatalogFilter(tables: ['orders']))->matchesTable($entry));
        self::assertTrue((new CatalogFilter())->matchesTable($entry));
    }

    public function testMatchesSinkComparesTheCallIdentifier(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(sinks: ['pdo.query']))->matchesSink($entry));
        self::assertFalse((new CatalogFilter(sinks: ['pdo.exec']))->matchesSink($entry));
    }

    public function testMatchesSeverityKeepsWhatIsAtLeastAsSevere(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 'pdo.query'), [
            Finding::of(FindingRule::DynamicSql, 'x'),
        ]);
        self::assertTrue((new CatalogFilter(minimumSeverity: Severity::Medium))->matchesSeverity($entry));
        self::assertFalse((new CatalogFilter(minimumSeverity: Severity::High))->matchesSeverity($entry));
        self::assertTrue((new CatalogFilter())->matchesSeverity($entry));
    }

    public function testMatchesRequiresEveryGivenCriterion(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), ['users'], [], new CallSite('src/a.php', 1, 'App\\R::find', 'pdo.query'), []);
        self::assertTrue((new CatalogFilter(['App'], ['find'], ['src'], [StatementKind::Select], ['users'], ['pdo.query']))->matches($entry));
        self::assertFalse((new CatalogFilter(['App'], ['save']))->matches($entry));
    }
}
