<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\QualifiedTemporaryName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\StrictTypeViolation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownTableOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(TableProblems::class)]
#[Medium]
final class TableProblemsTest extends TestCase
{
    public function testReportFindsNothingInASoundDefinition(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, a TEXT) STRICT', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testReportFindsAnUnknownOptionAndANonStandardStrictType(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a VARCHAR(10), b) STRICT, fast', []);

        self::assertSame([UnknownTableOption::class, StrictTypeViolation::class, StrictTypeViolation::class], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic::class, $operation->facts->diagnostics));
    }

    public function testReportFindsEveryPrimaryKeyFlaw(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $flaw = static fn (string $sql): ?PrimaryKeyFlaw => array_map(static fn (Diagnostic $diagnostic): ?PrimaryKeyFlaw => $diagnostic instanceof PrimaryKeyProblem ? $diagnostic->flaw : null, $semantics->analyze($sql, [])->facts->diagnostics)[0] ?? null;

        self::assertSame(PrimaryKeyFlaw::Repeated, $flaw('CREATE TABLE t (a PRIMARY KEY, b PRIMARY KEY)'));
        self::assertSame(PrimaryKeyFlaw::MissingWithoutRowid, $flaw('CREATE TABLE t (a) WITHOUT ROWID'));
        self::assertSame(PrimaryKeyFlaw::AutoincrementNotIntegerKey, $flaw('CREATE TABLE t (a INTEGER PRIMARY KEY DESC AUTOINCREMENT)'));
        self::assertSame(PrimaryKeyFlaw::AutoincrementWithoutRowid, $flaw('CREATE TABLE t (a INTEGER PRIMARY KEY AUTOINCREMENT) WITHOUT ROWID'));
    }

    public function testTemporaryReportsOnlyAQualifierOtherThanTemp(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(QualifiedTemporaryName::class, $semantics->analyze('CREATE TEMP TABLE main.t (a)')->facts->diagnostics[0]);
        self::assertSame([], $semantics->analyze('CREATE TEMP TABLE TEMP.t (a)')->facts->diagnostics);
        self::assertSame([], $semantics->analyze('CREATE TABLE main.t (a)')->facts->diagnostics);
    }

    public function testTemporaryReportsThroughTheDerivation(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        (new TableProblems())->temporary(new QualifiedName(new Name('t'), new Name('aux')), true, $derivation);

        self::assertInstanceOf(QualifiedTemporaryName::class, $derivation->facts()->diagnostics[0]);
    }

    public function testDuplicatesReportsEachLaterColumnWithATakenName(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::Sqlite))->context());
        (new TableProblems())->duplicates([new ColumnDefinition(new Name('a')), new ColumnDefinition(new Name('b')), new ColumnDefinition(new Name('A')), new ColumnDefinition(new Name('a'))], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(DuplicateColumn::class, $diagnostics[0]);
        self::assertSame('A', $diagnostics[0]->column->value);
    }
}
