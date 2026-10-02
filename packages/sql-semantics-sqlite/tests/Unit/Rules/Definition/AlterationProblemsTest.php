<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\AlterationProblems;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AlterAddColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationObstacle;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationRefused;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\StrictTypeViolation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(AlterationProblems::class)]
#[Medium]
final class AlterationProblemsTest extends TestCase
{
    public function testOccurrencesCountsTheColumnsWithANameWithoutRegardToCase(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([$semantics->analyze('CREATE TABLE t (a, b)')]));
        $fact = (new TableShapes())->target($derivation, new QualifiedName(new Name('t')));

        self::assertSame(1, (new AlterationProblems())->occurrences($fact, new Name('A'), $derivation));
        self::assertSame(0, (new AlterationProblems())->occurrences($fact, new Name('c'), $derivation));
    }

    public function testOccurrencesIsNullWhileTheColumnsAreNotKnown(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $open = new Derivation($semantics->context());
        $missing = new Derivation($semantics->context([]));

        self::assertNull((new AlterationProblems())->occurrences((new TableShapes())->target($open, new QualifiedName(new Name('t'))), new Name('a'), $open));
        self::assertNull((new AlterationProblems())->occurrences((new TableShapes())->target($missing, new QualifiedName(new Name('t'))), new Name('a'), $missing));
    }

    public function testStrictIsReadFromTheDeclaredColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $strict = new Derivation($semantics->context([$semantics->analyze('CREATE TABLE t (a INT) STRICT')]));
        $plain = new Derivation($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')]));

        self::assertTrue((new AlterationProblems())->strict((new TableShapes())->target($strict, new QualifiedName(new Name('t')))));
        self::assertFalse((new AlterationProblems())->strict((new TableShapes())->target($plain, new QualifiedName(new Name('t')))));
    }

    public function testSlotHasTheRecordedTypeAndTheNullFactOfTheNewColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([$semantics->analyze('CREATE TABLE t (a)')]));
        $statement = $semantics->analyze('ALTER TABLE t ADD b integer NOT NULL DEFAULT 1')->statement;

        self::assertInstanceOf(AlterAddColumn::class, $statement);
        $slot = (new AlterationProblems())->slot((new TableShapes())->target($derivation, new QualifiedName(new Name('t'))), $statement->column);
        self::assertSame('b', $slot->name?->value);
        self::assertSame(Nullability::NotNull, $slot->nullability);
        self::assertInstanceOf(Known::class, $slot->type);
        self::assertSame('INTEGER', $slot->type->descriptor->name());
    }

    public function testAddedReportsEveryObstacleOfTheNewColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INT) STRICT');
        $obstacles = static fn (string $sql): array => array_map(static fn (Diagnostic $diagnostic): string => $diagnostic instanceof AlterationRefused ? $diagnostic->obstacle->name : $diagnostic::class, $semantics->analyze($sql, [$table])->facts->diagnostics);

        self::assertSame([], $obstacles('ALTER TABLE t ADD b INT NOT NULL DEFAULT 0'));
        self::assertSame([], $obstacles('ALTER TABLE t ADD b INT AS (a) VIRTUAL NOT NULL'));
        self::assertSame([AlterationObstacle::UniqueColumn->name], $obstacles('ALTER TABLE t ADD b INT UNIQUE'));
        self::assertSame([AlterationObstacle::PrimaryKeyColumn->name], $obstacles('ALTER TABLE t ADD b INT PRIMARY KEY'));
        self::assertSame([AlterationObstacle::StoredColumn->name], $obstacles('ALTER TABLE t ADD b INT AS (a) STORED'));
        self::assertSame([AlterationObstacle::NotNullWithoutDefault->name], $obstacles('ALTER TABLE t ADD b INT NOT NULL'));
        self::assertSame([AlterationObstacle::NotNullWithoutDefault->name], $obstacles('ALTER TABLE t ADD b INT NOT NULL DEFAULT NULL'));
        self::assertSame([DuplicateColumn::class], $obstacles('ALTER TABLE t ADD A INT'));
        self::assertSame([StrictTypeViolation::class], $obstacles('ALTER TABLE t ADD b VARCHAR'));
    }

    public function testDroppedReportsAnUnknownColumnAndTheOnlyColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $one = new Derivation($semantics->context([$semantics->analyze('CREATE TABLE t (a)')]));
        $fact = (new TableShapes())->target($one, new QualifiedName(new Name('t')));
        (new AlterationProblems())->dropped($fact, new Name('zz'), $one);
        (new AlterationProblems())->dropped($fact, new Name('a'), $one);
        $diagnostics = $one->facts()->diagnostics;

        self::assertInstanceOf(UnknownColumn::class, $diagnostics[0]);
        self::assertInstanceOf(AlterationRefused::class, $diagnostics[1]);
        self::assertSame(AlterationObstacle::LastColumn, $diagnostics[1]->obstacle);
    }

    public function testRenamedReportsAnUnknownOldNameAndATakenNewName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $derivation = new Derivation($semantics->context([$semantics->analyze('CREATE TABLE t (a, b)')]));
        $fact = (new TableShapes())->target($derivation, new QualifiedName(new Name('t')));
        (new AlterationProblems())->renamed($fact, new Name('zz'), new Name('c'), $derivation);
        (new AlterationProblems())->renamed($fact, new Name('a'), new Name('b'), $derivation);
        (new AlterationProblems())->renamed($fact, new Name('a'), new Name('A'), $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(UnknownColumn::class, $diagnostics[0]);
        self::assertInstanceOf(DuplicateColumn::class, $diagnostics[1]);
    }
}
