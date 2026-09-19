<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\EntryFactory;
use SqlCatalog\Analysis\QueryRecord;
use SqlCatalog\Catalog\CallSite;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\EntryIdentity;
use SqlCatalog\Catalog\Finding;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Placeholder;
use SqlCatalog\Catalog\Severity;
use SqlCatalog\Catalog\ValueDomain;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Sql\PlaceholderRef;
use SqlCatalog\Sql\PlaceholderScanner;
use SqlCatalog\Sql\SqlLexer;
use SqlCatalog\Sql\SqlToken;
use SqlCatalog\Sql\StatementKind;
use SqlCatalog\Sql\StatementKindReader;
use SqlCatalog\Sql\TableReader;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(EntryFactory::class)]
#[UsesClass(QueryRecord::class)]
#[UsesClass(CallSite::class)]
#[UsesClass(CatalogEntry::class)]
#[UsesClass(EntryIdentity::class)]
#[UsesClass(Finding::class)]
#[UsesClass(FindingRule::class)]
#[UsesClass(Placeholder::class)]
#[UsesClass(Severity::class)]
#[UsesClass(ValueDomain::class)]
#[UsesClass(Domain::class)]
#[UsesClass(PlaceholderRef::class)]
#[UsesClass(PlaceholderScanner::class)]
#[UsesClass(SqlLexer::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(StatementKindReader::class)]
#[UsesClass(TableReader::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(\SqlCatalog\Evaluation\LiteralTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\OpaqueTerm::class)]
#[UsesClass(\SqlCatalog\Evaluation\PatternTerm::class)]
#[UsesClass(\SqlCatalog\Text\TextGeneralization::class)]
#[UsesClass(Origin::class)]
final class EntryFactoryTest extends TestCase
{
    public function testBuildTurnsRecordsIntoEntries(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', TextPattern::fromText('SELECT id FROM users'));
        $entries = (new EntryFactory())->build([$record]);
        self::assertCount(1, $entries);
        self::assertSame(StatementKind::Select, $entries[0]->kind);
        self::assertSame(['users'], $entries[0]->tables);
    }

    public function testBuildOfNothingIsEmpty(): void
    {
        self::assertSame([], (new EntryFactory())->build([]));
    }

    public function testMergeFoldsTwoReadingsOfTheSameStatement(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.prepare');
        $first = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $first->bind([Domain::literal(1)], []);
        $second = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $second->bind([Domain::literal(2)], []);
        $merged = (new EntryFactory())->merge([$first, $second]);
        self::assertCount(1, $merged);
        self::assertCount(2, $merged[0]->positional()[0]->terms);
    }

    public function testGroupBySiteKeepsTheCallsApart(): void
    {
        $first = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'one', TextPattern::fromText('SELECT 1'));
        $second = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'two', TextPattern::fromText('SELECT 2'));
        self::assertSame(['one', 'two'], array_keys((new EntryFactory())->groupBySite([$first, $second])));
    }

    public function testBuildOneReadsTheStatementKindTheCallImplies(): void
    {
        $record = new QueryRecord(
            new CallSite('a.php', 1, 'f', 'laravel.db.select'),
            'k',
            TextPattern::fromText('users WHERE 1'),
            StatementKind::Select,
        );
        self::assertSame(StatementKind::Select, (new EntryFactory())->buildOne($record)->kind);
    }

    public function testBindPlaceholdersLinesValuesUpByPosition(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT ?, ?'));
        $record->bind([Domain::literal('a'), Domain::literal('b')], []);
        $bound = (new EntryFactory())->bindPlaceholders($record->pattern, $record);
        self::assertSame(['a'], $bound[0]->value?->values);
        self::assertSame(['b'], $bound[1]->value?->values);
    }

    public function testBindPlaceholdersLinesValuesUpByName(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT :id'));
        $record->bind([], ['id' => Domain::literal(7)]);
        $bound = (new EntryFactory())->bindPlaceholders($record->pattern, $record);
        self::assertSame([7], $bound[0]->value?->values);
    }

    public function testNumberedValueReadsAPositionalValueByItsNumber(): void
    {
        $factory = new EntryFactory();
        self::assertSame('a', $factory->numberedValue('1', [Domain::literal('a')])?->soleLiteral()?->value);
        self::assertNull($factory->numberedValue('id', [Domain::literal('a')]));
        self::assertNull($factory->numberedValue(null, []));
        self::assertNull($factory->numberedValue('9', [Domain::literal('a')]));
    }

    public function testFindingsReportADynamicStatement(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM users WHERE a = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Parameter, TypeShape::unknown())));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);
        $findings = (new EntryFactory())->findings($pattern, $record, []);
        self::assertSame(FindingRule::DynamicSql, $findings[0]->rule);
    }

    public function testFindingsReportExternalInput(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM users WHERE a = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);
        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, []),
        );
        self::assertContains(FindingRule::ExternalInput, $rules);
    }

    public function testFindingsReportAStatementThatDidNotResolveAtAll(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::Parameter, TypeShape::unknown()));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);
        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, []),
        );
        self::assertContains(FindingRule::UnresolvedSql, $rules);
    }

    public function testFindingsAreEmptyForAResolvedStatement(): void
    {
        $pattern = TextPattern::fromText('SELECT 1');
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);
        self::assertSame([], (new EntryFactory())->findings($pattern, $record, []));
    }

    public function testCountMismatchReportsAStatementBoundWithTheWrongNumberOfValues(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT ?'));
        $record->bind([Domain::literal(1), Domain::literal(2)], []);
        $placeholders = [new Placeholder('?', 0, null, null)];
        self::assertNotNull((new EntryFactory())->countMismatch($record, $placeholders));
    }

    public function testCountMismatchStaysQuietWhenTheCountsAgree(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT ?'));
        $record->bind([Domain::literal(1)], []);
        self::assertNull((new EntryFactory())->countMismatch($record, [new Placeholder('?', 0, null, null)]));
    }

    public function testCountMismatchStaysQuietWhenNothingWasBound(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', TextPattern::fromText('SELECT ?'));
        self::assertNull((new EntryFactory())->countMismatch($record, [new Placeholder('?', 0, null, null)]));
    }

    public function testCountMismatchStaysQuietWhenTheCallSiteHasAlternatives(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.prepare');
        $first = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'));
        $first->bind([Domain::literal(1)], []);
        $second = new QueryRecord($site, 'k', TextPattern::fromText('SELECT ?'));
        $second->bind([Domain::literal(1)], []);
        $entries = (new EntryFactory())->build([$first, $second]);
        $rules = array_merge(...array_map(
            static fn (CatalogEntry $entry): array => array_map(
                static fn (Finding $finding): FindingRule => $finding->rule,
                $entry->findings,
            ),
            $entries,
        ));
        self::assertNotContains(FindingRule::PlaceholderCountMismatch, $rules);
    }

    public function testDropSupersededRemovesAReadingAResolvedOneCovers(): void
    {
        $exact = TextPattern::fromText('SELECT * FROM users');
        $shape = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $entries = (new EntryFactory())->build([
            new QueryRecord($site, 'k', $shape),
            new QueryRecord($site, 'k', $exact),
        ]);
        self::assertCount(1, $entries);
        self::assertTrue($entries[0]->isExact());
    }

    public function testDropSupersededIsCalledWithTheEntries(): void
    {
        $factory = new EntryFactory();
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $shape = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        $entries = [
            $factory->buildOne(new QueryRecord($site, 'k', $shape)),
            $factory->buildOne(new QueryRecord($site, 'k', TextPattern::fromText('SELECT * FROM users'))),
        ];

        $kept = $factory->dropSuperseded($entries);

        self::assertCount(1, $kept);
        self::assertSame('SELECT * FROM users', $kept[0]->sql());
    }

    public function testIsSupersededAtIgnoresAnUnrelatedStatement(): void
    {
        $factory = new EntryFactory();
        $shape = TextPattern::fromText('SELECT * FROM orders WHERE a = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $entry = $factory->buildOne(new QueryRecord($site, 'k', $shape));
        $other = $factory->buildOne(new QueryRecord($site, 'k', TextPattern::fromText('SELECT * FROM users')));
        self::assertFalse($factory->isSupersededAt($entry, [$entry, $other]));
    }

    public function testCoversChecksThatEveryResolvedRunAppearsInOrder(): void
    {
        $factory = new EntryFactory();
        $shape = TextPattern::fromText('SELECT ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())))
            ->concat(TextPattern::fromText(' FROM t'));
        self::assertTrue($factory->covers($shape, TextPattern::fromText('SELECT a FROM t')));
        self::assertFalse($factory->covers($shape, TextPattern::fromText('SELECT a FROM u')));
    }
}
