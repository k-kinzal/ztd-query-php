<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\GeneratedColumnProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyFlaw;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\QualifiedTemporaryName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\StrictTypeViolation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownTableOption;

#[CoversClass(TableProblems::class)]
#[Medium]
final class TableProblemsTest extends TestCase
{
    public function testReportFindsNothingInASoundDefinition(): void
    {
        $operation = (new Semantics())->inspect('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, a TEXT) STRICT', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testReportFindsAnUnknownOptionAndANonStandardStrictType(): void
    {
        $operation = (new Semantics())->inspect('CREATE TABLE t (a VARCHAR(10), b) STRICT, fast', []);

        self::assertSame([UnknownTableOption::class, StrictTypeViolation::class, StrictTypeViolation::class], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic::class, $operation->facts->diagnostics));
    }

    public function testReportFindsEveryPrimaryKeyFlaw(): void
    {
        $semantics = new Semantics();
        $flaw = static fn (string $sql): ?PrimaryKeyFlaw => array_map(static fn (Diagnostic $diagnostic): ?PrimaryKeyFlaw => $diagnostic instanceof PrimaryKeyProblem ? $diagnostic->flaw : null, $semantics->inspect($sql, [])->facts->diagnostics)[0] ?? null;

        self::assertSame(PrimaryKeyFlaw::Repeated, $flaw('CREATE TABLE t (a PRIMARY KEY, b PRIMARY KEY)'));
        self::assertSame(PrimaryKeyFlaw::MissingWithoutRowid, $flaw('CREATE TABLE t (a) WITHOUT ROWID'));
        self::assertSame(PrimaryKeyFlaw::AutoincrementNotIntegerKey, $flaw('CREATE TABLE t (a INTEGER PRIMARY KEY DESC AUTOINCREMENT)'));
        self::assertSame(PrimaryKeyFlaw::AutoincrementWithoutRowid, $flaw('CREATE TABLE t (a INTEGER PRIMARY KEY AUTOINCREMENT) WITHOUT ROWID'));
    }

    public function testGeneratedReportsAKeyColumnThatIsGeneratedAndATableWithoutAPlainColumn(): void
    {
        $semantics = new Semantics();
        $problems = static fn (string $sql): array => array_map(static fn (Diagnostic $diagnostic): array => $diagnostic instanceof GeneratedColumnProblem ? [$diagnostic->flaw->name, $diagnostic->column?->value] : [$diagnostic::class], $semantics->inspect($sql, [])->facts->diagnostics);

        self::assertSame([['InPrimaryKey', 'b']], $problems('CREATE TABLE t (a, b AS (a) PRIMARY KEY)'));
        self::assertSame([['InPrimaryKey', 'c']], $problems('CREATE TABLE t (a, b AS (a), c AS (a), PRIMARY KEY (a, "c"))'));
        self::assertSame([['InPrimaryKey', 'a'], ['NoPlainColumn', null]], $problems('CREATE TABLE t (a AS (1) PRIMARY KEY)'));
        self::assertSame([], $problems('CREATE TABLE t (a PRIMARY KEY, b AS (a))'));
    }

    public function testTemporaryReportsOnlyAQualifierOtherThanTemp(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(QualifiedTemporaryName::class, $semantics->inspect('CREATE TEMP TABLE main.t (a)')->facts->diagnostics[0]);
        self::assertSame([], $semantics->inspect('CREATE TEMP TABLE TEMP.t (a)')->facts->diagnostics);
        self::assertSame([], $semantics->inspect('CREATE TABLE main.t (a)')->facts->diagnostics);
    }

    public function testTemporaryReportsThroughTheDerivation(): void
    {
        $derivation = new Derivation((new Semantics())->context());
        (new TableProblems())->temporary(new QualifiedName(new Name('t'), new Name('aux')), true, $derivation);

        self::assertInstanceOf(QualifiedTemporaryName::class, $derivation->facts()->diagnostics[0]);
    }

    public function testDuplicatesReportsEachLaterColumnWithATakenName(): void
    {
        $derivation = new Derivation((new Semantics())->context());
        (new TableProblems())->duplicates([new ColumnDefinition(new Name('a')), new ColumnDefinition(new Name('b')), new ColumnDefinition(new Name('A')), new ColumnDefinition(new Name('a'))], $derivation);
        $diagnostics = $derivation->facts()->diagnostics;

        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(DuplicateColumn::class, $diagnostics[0]);
        self::assertSame('A', $diagnostics[0]->column->value);
    }
}
