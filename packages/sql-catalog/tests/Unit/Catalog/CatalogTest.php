<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\AnalysisOptions;
use SqlCatalog\Analyzer;
use SqlCatalog\Catalog\AnalysisProblem;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

#[CoversClass(Catalog::class)]
#[UsesClass(AnalysisOptions::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(AnalysisProblem::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(\SqlCatalog\Analysis\FunctionModel\Registry::class)]
final class CatalogTest extends TestCase
{
    public function testEntriesAreReturnedInTheOrderTheyWereGiven(): void
    {
        $first = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('b.php', 2, 'f', 's'), []);
        $second = new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 1, 'f', 's'), []);
        self::assertSame([$first, $second], (new Catalog([$first, $second]))->entries());
    }

    public function testProblemsAreKept(): void
    {
        $problem = new AnalysisProblem('a.php', 'broken');
        self::assertSame([$problem], (new Catalog([], [$problem]))->problems());
    }

    public function testFindLooksUpAStatementByItsIdentifier(): void
    {
        $entry = new CatalogEntry('abc', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 's'), []);
        $catalog = new Catalog([$entry]);
        self::assertSame($entry, $catalog->find('abc'));
        self::assertNull($catalog->find('missing'));
    }

    public function testFilterKeepsOnlyWhatTheTestAccepts(): void
    {
        $kept = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 's'), []);
        $dropped = new CatalogEntry('b', StatementKind::Insert, TextPattern::fromText('INSERT INTO t VALUES (1)'), [], [], new CallSite('a.php', 2, 'f', 's'), []);
        $filtered = (new Catalog([$kept, $dropped]))->filter(
            static fn (CatalogEntry $entry): bool => $entry->kind === StatementKind::Select,
        );
        self::assertSame([$kept], $filtered->entries());
    }

    public function testMergeJoinsStatementsAndProblems(): void
    {
        $left = new Catalog([], [new AnalysisProblem('a.php', 'x')]);
        $right = new Catalog([], [new AnalysisProblem('b.php', 'y')]);
        self::assertCount(2, $left->merge($right)->problems());
    }

    public function testSortedOrdersByWhereTheStatementIsIssued(): void
    {
        $later = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('b.php', 2, 'f', 's'), []);
        $earlier = new CatalogEntry('b', StatementKind::Select, TextPattern::fromText('SELECT 2'), [], [], new CallSite('a.php', 1, 'f', 's'), []);
        $sorted = (new Catalog([$later, $earlier], [new AnalysisProblem('z.php', 'x'), new AnalysisProblem('a.php', 'y')]))->sorted();
        self::assertSame([$earlier, $later], $sorted->entries());
        self::assertSame('a.php', $sorted->problems()[0]->file);
    }

    public function testCountIsTheNumberOfStatements(): void
    {
        self::assertCount(0, new Catalog());
    }

    public function testGetIteratorWalksTheStatements(): void
    {
        $entry = new CatalogEntry('a', StatementKind::Select, TextPattern::fromText('SELECT 1'), [], [], new CallSite('a.php', 1, 'f', 's'), []);
        self::assertSame([$entry], iterator_to_array(new Catalog([$entry])));
    }
}
