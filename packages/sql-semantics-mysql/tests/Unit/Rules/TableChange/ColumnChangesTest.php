<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\ColumnChanges;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnChanges::class)]
#[Medium]
final class ColumnChangesTest extends TestCase
{
    public function testApplyAnswersTheShapeAfterTheChange(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ADD c INT AFTER a, DROP b', [$table]);

        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        self::assertSame(['a', 'c'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $alter->relationInformation()->shape->slots));
    }

    public function testCommandReportsNothingForAnUndeclaredTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t DROP x, ADD a INT, ADD a INT')->diagnostics()));
    }

    public function testChangeReportsAnAbsentColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t CHANGE x y INT, MODIFY a INT', [$table])->diagnostics()));
    }

    public function testDropReportsAColumnDroppedTwice(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column a does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t DROP a, DROP a', [$table])->diagnostics()));
    }

    public function testRenameReportsAnAbsentColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t RENAME COLUMN x TO y', [$table])->diagnostics()));
    }

    public function testAlteredAcceptsAnAddedColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ADD c INT, ALTER c SET DEFAULT 1, ALTER x DROP DEFAULT', [$table])->diagnostics()));
    }

    public function testUseReportsAColumnAnEarlierActionUsed(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column a does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t CHANGE a c INT, DROP a', [$table])->diagnostics()));
    }

    public function testFindComparesWithoutRegardToCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t DROP A, ALTER B DROP DEFAULT', [$table])->diagnostics()));
    }

    public function testIndexAnswersNullForNoColumn(): void
    {
        self::assertNull((new ColumnChanges())->index(0));
    }

    public function testInsertPlacesFirst(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t MODIFY b INT FIRST', [$table]);

        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        self::assertSame(['b', 'a'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $alter->relationInformation()->shape->slots));
    }

    public function testSlotAnswersTheDeclaredNameOfTheNewDefinition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t ADD c INT NOT NULL', [$table]);

        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        self::assertSame(Nullability::NotNull, $alter->relationInformation()->shape->slots[2]->nullability);
    }

    public function testDuplicatesReportsEveryLaterOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column a would be defined more than once.', 'Column A would be defined more than once.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ADD A INT, RENAME COLUMN b TO a', [$table])->diagnostics()));
    }

    public function testImplicitAnswersTheInvisibleColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, h INT INVISIBLE)');
        $alter = $semantics->analyze('ALTER TABLE t ADD i INT INVISIBLE, ALTER h SET DEFAULT (h + i)', [$table]);

        self::assertSame([], $alter->diagnostics());
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        self::assertSame(['a'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $alter->relationInformation()->shape->slots));
    }

    public function testShowMakesAColumnVisible(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, h INT INVISIBLE)');
        $alter = $semantics->analyze('ALTER TABLE t ALTER h SET VISIBLE, ALTER a SET INVISIBLE', [$table]);

        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        self::assertSame(['h'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $alter->relationInformation()->shape->slots));
    }

    public function testInvisibleKeepsAnInvisibleColumnInTheChecks(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column c would be defined more than once.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ADD c INT INVISIBLE, ADD c INT', [$table])->diagnostics()));
    }

    public function testUnknownReportsAnAbsentPosition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');

        self::assertSame(['Column x does not exist in the table.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ADD c INT AFTER x', [$table])->diagnostics()));
    }

    public function testReportReportsNothingForAnUndeclaredTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ADD c INT AFTER x')->diagnostics()));
    }
}
