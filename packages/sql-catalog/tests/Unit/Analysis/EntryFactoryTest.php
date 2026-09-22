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
#[UsesClass(\SqlCatalog\Catalog\Resolution::class)]
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

    public function testBuildGivesTwoIndistinguishableStatementsDistinctIdentifiers(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown()));
        $entries = (new EntryFactory())->build([
            new QueryRecord(new CallSite('a.php', 5, 'f', 'unreached'), 'a.php:40:unreached', $pattern),
            new QueryRecord(new CallSite('a.php', 5, 'f', 'unreached'), 'a.php:80:unreached', $pattern),
        ]);

        self::assertCount(2, $entries);
        self::assertNotSame($entries[0]->id, $entries[1]->id);
    }

    public function testRenumberKeepsEverythingButTheIdentifier(): void
    {
        $factory = new EntryFactory();
        $record = new QueryRecord(new CallSite('a.php', 5, 'f', 'pdo.query'), 'k', TextPattern::fromText('SELECT 1'));
        $entry = $factory->buildOne($record);

        $renumbered = $factory->renumber($entry, $record, 1);

        self::assertNotSame($entry->id, $renumbered->id);
        self::assertSame($entry->sql(), $renumbered->sql());
        self::assertSame($entry->kind, $renumbered->kind);
        self::assertSame($entry->site, $renumbered->site);
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

    public function testBindPlaceholdersLeavesAPlaceholderNothingWasBoundToEmpty(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT * FROM t WHERE a = ? AND b = ?'));
        $record->bindOne(2, Domain::literal('x'));

        $bound = (new EntryFactory())->bindPlaceholders($record->pattern, $record);

        self::assertNull($bound[0]->value);
        self::assertSame(['x'], $bound[1]->value?->values);
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

    public function testBuildKeepsBothReadingsOfTheSameCall(): void
    {
        $exact = TextPattern::fromText('SELECT * FROM users');
        $shape = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Property, TypeShape::unknown())));
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');

        $entries = (new EntryFactory())->build([
            new QueryRecord($site, 'k', $shape),
            new QueryRecord($site, 'k', $exact),
        ]);

        self::assertCount(2, $entries);
        self::assertSame([false, true], array_map(
            static fn (CatalogEntry $entry): bool => $entry->isExact(),
            $entries,
        ));
    }

    public function testBuildKeepsAnUndeterminedReadingBesideAResolvedOne(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $shape = TextPattern::fromHole(new TextHole(Origin::Parameter, TypeShape::unknown()));
        $entries = (new EntryFactory())->build([
            new QueryRecord($site, 'k', $shape),
            new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1')),
        ]);

        self::assertSame(['{$}', 'SELECT 1'], array_map(
            static fn (CatalogEntry $entry): string => $entry->sql(),
            $entries,
        ));
    }

    public function testBuildOneRecordsWhetherTheAlternativesAreOnesTheCodeCanReach(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $factory = new EntryFactory();

        self::assertTrue($factory->buildOne(new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1')))->correlated);
        self::assertFalse(
            $factory->buildOne(new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), null, true))->correlated,
        );
    }

    public function testBuildOneCarriesTheCallsThatWereFollowed(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $record = new QueryRecord($site, 'k', TextPattern::fromText('SELECT 1'), null, false, ['App\\R::find']);

        self::assertSame(['App\\R::find'], (new EntryFactory())->buildOne($record)->through);
    }

    public function testFindingsReportThatTheSearchDidNotClose(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);
        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, []),
        );

        self::assertContains(FindingRule::AnalysisIncomplete, $rules);
        self::assertNotContains(FindingRule::DynamicSql, $rules);
    }

    public function testBuildNamesALoneStatementAsItsOwnReadingWould(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', TextPattern::fromText('SELECT 1'));
        $factory = new EntryFactory();

        self::assertSame($factory->buildOne($record)->id, $factory->build([$record])[0]->id);
    }

    public function testBuildNumbersEachFurtherTwinOnceMore(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown()));
        $site = new CallSite('a.php', 5, 'f', 'unreached');
        $records = [
            new QueryRecord($site, 'a.php:40:unreached', $pattern),
            new QueryRecord($site, 'a.php:80:unreached', $pattern),
            new QueryRecord($site, 'a.php:120:unreached', $pattern),
        ];
        $factory = new EntryFactory();

        $entries = $factory->build($records);

        self::assertSame(
            [
                $factory->buildOne($records[0])->id,
                $factory->renumber($factory->buildOne($records[1]), $records[1], 1)->id,
                $factory->renumber($factory->buildOne($records[2]), $records[2], 2)->id,
            ],
            array_map(static fn (CatalogEntry $entry): string => $entry->id, $entries),
        );
    }

    public function testBuildReportsAStatementBoundWithTheWrongNumberOfValues(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT ?'));
        $record->bind([Domain::literal(1), Domain::literal(2)], []);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->build([$record])[0]->findings,
        );

        self::assertSame([FindingRule::PlaceholderCountMismatch], $rules);
    }

    public function testBuildOneReportsAStatementBoundWithTheWrongNumberOfValues(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT ?'));
        $record->bind([Domain::literal(1), Domain::literal(2)], []);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->buildOne($record)->findings,
        );

        self::assertSame([FindingRule::PlaceholderCountMismatch], $rules);
    }

    public function testMergeKeepsTwoStatementsApartWhenTheirKeyAndTextRunTogether(): void
    {
        $site = new CallSite('a.php', 1, 'f', 'pdo.query');
        $first = new QueryRecord($site, 'k', TextPattern::fromText('text:X'));
        $second = new QueryRecord($site, 'ktext:', TextPattern::fromText('X'));

        self::assertCount(2, (new EntryFactory())->merge([$first, $second]));
    }

    public function testBindPlaceholdersPrefersAValueBoundUnderTheNumberAsAName(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pg.query'), 'k', TextPattern::fromText('SELECT $1'));
        $record->bindOne(1, Domain::literal('by position'));
        $record->bindOne(':1', Domain::literal('by name'));

        $bound = (new EntryFactory())->bindPlaceholders($record->pattern, $record);

        self::assertSame(['by name'], $bound[0]->value?->values);
    }

    public function testBindPlaceholdersReadsANumberedPlaceholderByPosition(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pg.query'), 'k', TextPattern::fromText('SELECT $2, $1'));
        $record->bind([Domain::literal('a'), Domain::literal('b')], []);

        $bound = (new EntryFactory())->bindPlaceholders($record->pattern, $record);

        self::assertSame(['b'], $bound[0]->value?->values);
        self::assertSame(['a'], $bound[1]->value?->values);
    }

    public function testNumberedValueIgnoresANameThatOnlyStartsWithDigits(): void
    {
        self::assertNull((new EntryFactory())->numberedValue('1a', [Domain::literal('a')]));
    }

    public function testFindingsReportAStatementBoundWithTheWrongNumberOfValues(): void
    {
        $pattern = TextPattern::fromText('SELECT ?');
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', $pattern);
        $record->bind([Domain::literal(1), Domain::literal(2)], []);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, [new Placeholder('?', 0, null, null)]),
        );

        self::assertSame([FindingRule::PlaceholderCountMismatch], $rules);
    }

    public function testFindingsNeverCallAFullyResolvedStatementUnresolved(): void
    {
        $pattern = TextPattern::fromText('hello world');
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);

        self::assertSame([], (new EntryFactory())->findings($pattern, $record, []));
    }

    public function testFindingsReportExternalInputOnceHoweverManyValuesCarryIt(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM users WHERE a = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())))
            ->concat(TextPattern::fromText(' AND b = '))
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, []),
        );

        self::assertSame([FindingRule::DynamicSql, FindingRule::ExternalInput], $rules);
    }

    public function testFindingsReportAReadingALimitCutShortAlongsideAWrongCount(): void
    {
        $pattern = TextPattern::fromText('SELECT ?');
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', $pattern, truncated: true);
        $record->bind([Domain::literal(1), Domain::literal(2)], []);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, [new Placeholder('?', 0, null, null)]),
        );

        self::assertSame([FindingRule::AnalysisIncomplete, FindingRule::PlaceholderCountMismatch], $rules);
    }

    public function testFindingsReportAnIncompleteSearchOnceEvenWhenALimitAlsoCutItShort(): void
    {
        $pattern = TextPattern::fromText('SELECT * FROM ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::Budget, TypeShape::unknown())));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern, truncated: true);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, []),
        );

        self::assertSame([FindingRule::AnalysisIncomplete], $rules);
    }

    public function testFindingsReportACallNotAnalyzedWithoutClaimingALimitCutItShort(): void
    {
        $pattern = TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)'));
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.query'), 'k', $pattern, truncated: true);

        $rules = array_map(
            static fn (Finding $finding): FindingRule => $finding->rule,
            (new EntryFactory())->findings($pattern, $record, []),
        );

        self::assertSame([FindingRule::CallNotAnalyzed], $rules);
    }

    public function testCountMismatchCountsValuesBoundByPositionAndByName(): void
    {
        $record = new QueryRecord(new CallSite('a.php', 1, 'f', 'pdo.prepare'), 'k', TextPattern::fromText('SELECT ?, :id'));
        $record->bind([Domain::literal(1)], ['id' => Domain::literal(2)]);

        self::assertNull((new EntryFactory())->countMismatch($record, [
            new Placeholder('?', 0, null, null),
            new Placeholder(':id', 1, 'id', null),
        ]));
    }

    public function testNotAnalyzedReasonSaysTheAnalysisStoppedBeforeReadingTheCall(): void
    {
        $record = new QueryRecord(
            new CallSite('a.php', 1, 'f', 'pdo.query'),
            'a.php:10',
            TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown(), '$db->query($sql)')),
        );

        self::assertSame(
            '`$db->query($sql)` is a database call, but the analysis stopped before it read what the call is given.',
            (new EntryFactory())->notAnalyzedReason($record),
        );
    }

    public function testNotAnalyzedReasonSaysTheReceiverCouldNotBeIdentified(): void
    {
        $record = new QueryRecord(
            new CallSite('a.php', 1, 'f', CallSite::UNMATCHED),
            'a.php:10',
            TextPattern::fromHole(new TextHole(Origin::Unreached, TypeShape::unknown())),
        );

        self::assertSame(
            'The call is written the way a database call is written, but what it is called on could not be identified.',
            (new EntryFactory())->notAnalyzedReason($record),
        );
    }
}
