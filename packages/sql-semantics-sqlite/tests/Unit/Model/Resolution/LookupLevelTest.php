<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Resolution\ImplicitSlot;
use SqlSemantics\Platform\Sqlite\Model\Resolution\LookupLevel;
use SqlSemantics\Platform\Sqlite\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

#[CoversClass(LookupLevel::class)]
#[Small]
final class LookupLevelTest extends TestCase
{
    public function testAdmitsEveryOccurrenceWithoutAQualifier(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $scope = new Environment($context);
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([]));

        self::assertTrue((new LookupLevel($scope, new Name('a'), null, 0))->admits($scope, $relation, null));
    }

    public function testAdmitsAnAliasedOccurrenceByItsAliasOnly(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')], [], true, Comparison::AsciiInsensitive);
        $scope = new Environment($context);
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t')), new Name('x')), new RowShape([]), new Name('x'), new QualifiedName(new Name('t')));
        $level = new LookupLevel($scope, new Name('a'), null, 0);

        self::assertTrue($level->admits($scope, $relation, new QualifiedName(new Name('X'))));
        self::assertFalse($level->admits($scope, $relation, new QualifiedName(new Name('t'))));
        self::assertFalse($level->admits($scope, $relation, new QualifiedName(new Name('x'), new Name('main'))));
    }

    public function testAdmitsAPlainOccurrenceByItsNameAndSchema(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $scope = new Environment($context);
        $unqualified = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([]), null, new QualifiedName(new Name('t')));
        $qualified = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'), new Name('aux'))), new RowShape([]), null, new QualifiedName(new Name('t'), new Name('aux')));
        $nameless = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([]));
        $level = new LookupLevel($scope, new Name('a'), null, 0);

        self::assertTrue($level->admits($scope, $unqualified, new QualifiedName(new Name('t'))));
        self::assertTrue($level->admits($scope, $unqualified, new QualifiedName(new Name('t'), new Name('main'))));
        self::assertTrue($level->admits($scope, $qualified, new QualifiedName(new Name('t'), new Name('aux'))));
        self::assertFalse($level->admits($scope, $qualified, new QualifiedName(new Name('t'), new Name('main'))));
        self::assertFalse($level->admits($scope, $unqualified, new QualifiedName(new Name('u'))));
        self::assertFalse($level->admits($scope, $nameless, new QualifiedName(new Name('t'))));
    }

    public function testFoundListsTheMatchingSlotsOfEveryAdmittedOccurrenceAtTheGivenDepth(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')], [], true, Comparison::Sensitive, Comparison::AsciiInsensitive);
        $first = new TableInput(new QualifiedName(new Name('t')));
        $firstSlot = new OutputSlot(new Name('A'), new Known(Storage::Integer), Nullability::NotNull);
        $second = new TableInput(new QualifiedName(new Name('u')));
        $secondSlot = new OutputSlot(new Name('a'), new Known(Storage::Text), Nullability::Nullable);
        $scope = new Environment($context, null, [
            new VisibleRelation($first, new RowShape([$firstSlot, new OutputSlot(new Name('b'), new Known(Storage::Text), Nullability::Nullable)])),
            new VisibleRelation($second, new RowShape([$secondSlot])),
        ]);

        $found = (new LookupLevel($scope, new Name('a'), null, 2))->found();

        self::assertCount(2, $found);
        self::assertSame($firstSlot, $found[0]->slot);
        self::assertSame($first, $found[0]->relation);
        self::assertSame($secondSlot, $found[1]->slot);
        self::assertSame(2, $found[1]->depth);
    }

    public function testFoundSearchesImplicitSlotsOnlyWhenNoDeclaredSlotMatches(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $declared = new OutputSlot(new Name('rowid'), new Known(Storage::Text), Nullability::Nullable);
        $implicit = new OutputSlot(new Name('rowid'), new Known(Storage::Integer), Nullability::NotNull);
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([$declared]), null, null, [], [new ImplicitSlot([new Name('rowid'), new Name('oid')], $implicit)]);
        $scope = new Environment($context, null, [$relation]);

        self::assertSame($declared, (new LookupLevel($scope, new Name('rowid'), null, 0))->found()[0]->slot);
        self::assertSame($implicit, (new LookupLevel($scope, new Name('oid'), null, 0))->found()[0]->slot);
    }

    public function testOpenListsTheIncompleteOccurrencesWithoutAMatch(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $open = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([], [new UndeclaredRelation(new QualifiedName(new Name('t')))]));
        $matching = new VisibleRelation(new TableInput(new QualifiedName(new Name('u'))), new RowShape([new OutputSlot(new Name('a'), new Known(Storage::Integer), Nullability::NotNull)], [new UndeclaredRelation(new QualifiedName(new Name('u')))]));
        $scope = new Environment($context, null, [$open, $matching]);

        $level = new LookupLevel($scope, new Name('a'), null, 0);

        self::assertSame([$open], $level->open());
        self::assertCount(1, $level->found());
        self::assertSame([], (new LookupLevel($scope, new Name('a'), new QualifiedName(new Name('u')), 0))->open());
    }

    public function testOpenListsAnOccurrenceWithASlotWhoseNameIsUndecided(): void
    {
        $context = new AnalysisContext(new LanguageProfile(GrammarRelease::Sqlite3472), [new Name('main')]);
        $missing = new SessionState('the client character set');
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('d'))), new RowShape([new OutputSlot(null, new Known(Storage::Text), Nullability::NotNull, null, null, [$missing])]));

        $level = new LookupLevel(new Environment($context, null, [$relation]), new Name('x'), null, 0);

        self::assertSame([$relation], $level->open());
    }

    public function testUndecidedCollectsTheMissingInputsOfTheShapeAndOfUnnamedSlots(): void
    {
        $shapeMissing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        $nameMissing = new SessionState('the client character set');
        $relation = new VisibleRelation(new TableInput(new QualifiedName(new Name('t'))), new RowShape([new OutputSlot(null, new Known(Storage::Text), Nullability::NotNull, null, null, [$nameMissing])], [$shapeMissing]));

        self::assertSame([$shapeMissing, $nameMissing], LookupLevel::undecided($relation));
    }
}
