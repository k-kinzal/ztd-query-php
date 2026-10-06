<?php

declare(strict_types=1);

namespace Tests\Unit\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Resolution\ColumnLookup;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnLookup::class)]
#[Small]
final class ColumnLookupTest extends TestCase
{
    public function testFindResolvesTheOnlySlotWithTheName(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $relation = new TableInput(new QualifiedName(new Name('t')));
        $slot = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);
        $environment = new Environment($context, null, [new VisibleRelation($relation, new RowShape([$slot]))]);

        $resolution = (new ColumnLookup())->find($environment, new Name('a'));

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($relation, $resolution->relation);
        self::assertSame($slot, $resolution->slot);
        self::assertSame(0, $resolution->depth);
    }

    public function testFindIsAmbiguousForSeveralSlotsAtOnePosition(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $first = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]));
        $second = new VisibleRelation(new TableInput(new QualifiedName(new Name('u'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Text), Nullability::Nullable)]));
        $environment = new Environment($context, null, [$first, $second]);

        $resolution = (new ColumnLookup())->find($environment, new Name('a'));

        self::assertInstanceOf(AmbiguousColumn::class, $resolution);
        self::assertSame([$first->relation, $second->relation], [$resolution->candidates[0]->relation, $resolution->candidates[1]->relation]);
    }

    public function testFindContinuesInTheEnclosingPositionWithAGreaterDepth(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $outerRelation = new TableInput(new QualifiedName(new Name('t')));
        $outer = new Environment($context, null, [new VisibleRelation($outerRelation, new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]))]);
        $inner = new Environment($context, $outer, [new VisibleRelation(new TableInput(new QualifiedName(new Name('u'))), new RowShape([new OutputSlot(new Name('b'), new Known(Storage::Integer), Nullability::NotNull)]))]);

        $resolution = (new ColumnLookup())->find($inner, new Name('a'));

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($outerRelation, $resolution->relation);
        self::assertSame(1, $resolution->depth);
    }

    public function testFindPrefersTheNearerPositionOverAFartherSlot(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $outer = new Environment($context, null, [new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]))]);
        $innerRelation = new TableInput(new QualifiedName(new Name('u')));
        $inner = new Environment($context, $outer, [new VisibleRelation($innerRelation, new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Text), Nullability::Nullable)]))]);

        $resolution = (new ColumnLookup())->find($inner, new Name('a'));

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($innerRelation, $resolution->relation);
        self::assertSame(0, $resolution->depth);
    }

    public function testFindIsMissingOnlyWhenEverySearchedOccurrenceIsComplete(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $environment = new Environment($context, null, [new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]))]);

        $resolution = (new ColumnLookup())->find($environment, new Name('b'), new QualifiedName(new Name('t')));

        self::assertInstanceOf(MissingColumn::class, $resolution);
        self::assertSame('Column t.b does not exist.', $resolution->message());
    }

    public function testFindIsConditionalWhileANearerOccurrenceIsIncompletelyKnown(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $outerRelation = new TableInput(new QualifiedName(new Name('t')));
        $outerSlot = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);
        $outer = new Environment($context, null, [new VisibleRelation($outerRelation, new RowShape([$outerSlot]))]);
        $innerRelation = new TableInput(new QualifiedName(new Name('u')));
        $missing = new UndeclaredRelation(new QualifiedName(new Name('u')));
        $inner = new Environment($context, $outer, [new VisibleRelation($innerRelation, new RowShape([], [$missing]))]);

        $resolution = (new ColumnLookup())->find($inner, new Name('a'));

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertCount(1, $resolution->candidates);
        self::assertSame($outerSlot, $resolution->candidates[0]->slot);
        self::assertSame(1, $resolution->candidates[0]->depth);
        self::assertSame([$innerRelation], $resolution->relations);
        self::assertSame([$missing], $resolution->missing);
    }

    public function testFindIsConditionalWithoutCandidatesWhenNothingIsKnown(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $environment = new Environment($context, null, [new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([], [$missing]))]);

        $resolution = (new ColumnLookup())->find($environment, new Name('a'));

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
        self::assertSame([$missing], $resolution->missing);
    }

    public function testFindAdmitsOnlyTheOccurrenceTheQualifierNames(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $aliased = new TableInput(new QualifiedName(new Name('t')), new Name('x'));
        $plain = new TableInput(new QualifiedName(new Name('u')));
        $environment = new Environment($context, null, [
            new VisibleRelation($aliased, new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]), new Name('x')),
            new VisibleRelation($plain, new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Text), Nullability::Nullable)]), null, new QualifiedName(new Name('u'))),
        ]);

        $byAlias = (new ColumnLookup())->find($environment, new Name('a'), new QualifiedName(new Name('x')));
        $byName = (new ColumnLookup())->find($environment, new Name('a'), new QualifiedName(new Name('u')));

        self::assertInstanceOf(ResolvedColumn::class, $byAlias);
        self::assertSame($aliased, $byAlias->relation);
        self::assertInstanceOf(ResolvedColumn::class, $byName);
        self::assertSame($plain, $byName->relation);
        self::assertInstanceOf(MissingColumn::class, (new ColumnLookup())->find($environment, new Name('a'), new QualifiedName(new Name('t'))));
    }

    public function testFindSkipsHiddenSlotsForAnUnqualifiedName(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $relation = new TableInput(new QualifiedName(new Name('t')), new Name('x'));
        $hidden = new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull);
        $environment = new Environment($context, null, [new VisibleRelation($relation, new RowShape([$hidden]), new Name('x'), null, [0])]);

        $unqualified = (new ColumnLookup())->find($environment, new Name('a'));
        $qualified = (new ColumnLookup())->find($environment, new Name('a'), new QualifiedName(new Name('x')));

        self::assertInstanceOf(MissingColumn::class, $unqualified);
        self::assertInstanceOf(ResolvedColumn::class, $qualified);
        self::assertSame($hidden, $qualified->slot);
    }

    public function testFindUsesAnImplicitSlotWhenNoShapeSlotHasTheName(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $relation = new TableInput(new QualifiedName(new Name('t')));
        $rowid = new OutputSlot(new Name('rowid'), new Known(Storage::Integer), Nullability::NotNull);
        $environment = new Environment($context, null, [new VisibleRelation($relation, new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)]), null, null, [], [new ImplicitSlot([new Name('rowid'), new Name('oid')], $rowid)])]);

        $resolution = (new ColumnLookup())->find($environment, new Name('oid'));

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($rowid, $resolution->slot);
    }

    public function testConditionalCollectsTheRelationsAndMissingInputsOfEveryOpenOccurrence(): void
    {
        $first = new TableInput(new QualifiedName(new Name('t')));
        $second = new TableInput(new QualifiedName(new Name('u')));
        $missingFirst = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $missingSecond = new UndeclaredRelation(new QualifiedName(new Name('u')));

        $resolution = (new ColumnLookup())->conditional(new Name('a'), [], [
            new VisibleRelation($first, new RowShape([], [$missingFirst])),
            new VisibleRelation($second, new RowShape([], [$missingSecond])),
        ]);

        self::assertSame('a', $resolution->name->value);
        self::assertSame([$first, $second], $resolution->relations);
        self::assertSame([$missingFirst, $missingSecond], $resolution->missing);
    }
}
