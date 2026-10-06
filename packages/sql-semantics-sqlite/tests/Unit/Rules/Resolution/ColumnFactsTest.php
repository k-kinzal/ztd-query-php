<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnFacts::class)]
#[Medium]
final class ColumnFactsTest extends TestCase
{
    public function testOfAnswersTheSlotOfAResolvedColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT b FROM t', [$t]);
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $fact = (new ColumnFacts())->of($resolution);
        self::assertSame($resolution, $fact->resolution);
        self::assertSame($resolution->slot->type, $fact->type);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(ColumnDomain::class, $fact->type->descriptor);
        self::assertSame('TEXT', $fact->type->descriptor->name());
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testOfAnswersTheFieldOfAnAliasTarget(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a AS q FROM t WHERE q = 1', [$t]);

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(Binary::class, $query->statement->where);
        $resolution = $query->facts->scalar($query->statement->where->left)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        $fact = (new ColumnFacts())->of($resolution);
        self::assertSame($query->field('q'), $resolution->field);
        self::assertSame($resolution->field->type, $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame($resolution, $fact->resolution);
    }

    public function testOfDependsOnTheMissingInputsOfAConditionalColumn(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t');
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        $fact = (new ColumnFacts())->of($resolution);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame($resolution->missing, $fact->type->missing);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertSame($resolution, $fact->resolution);
    }

    public function testOfIsInvalidForAProblemResolution(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $ambiguous = $semantics->analyze('SELECT a FROM t, t AS t2', [$t])->field(0)->resolution;
        $missing = new MissingColumn(new Name('zz'));

        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous);
        $fact = (new ColumnFacts())->of($missing);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame($missing, $fact->type->cause);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertSame($missing, $fact->resolution);
        $problem = (new ColumnFacts())->of($ambiguous);
        self::assertInstanceOf(Invalid::class, $problem->type);
        self::assertSame($ambiguous, $problem->type->cause);
    }

    public function testDenotedLooksThroughGroupingsToTheNameUse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT ((b)), ("a"), (zz), b COLLATE nocase, (1), ("zz"), (true) FROM t', [$t]);

        self::assertInstanceOf(ResolvedColumn::class, $query->field(0)->resolution);
        self::assertSame($t->declarations()[0]->columns[2], $query->field(0)->column());
        self::assertSame('b', $query->field(0)->name?->value);
        self::assertSame($t->declarations()[0]->columns[1], $query->field(1)->column());
        self::assertInstanceOf(MissingColumn::class, $query->field(2)->resolution);
        self::assertNull($query->field(3)->resolution);
        self::assertNull($query->field(4)->resolution);
        self::assertNull($query->field(5)->resolution);
        self::assertNull($query->field(6)->resolution);
        self::assertCount(1, $query->facts->diagnostics);
    }

    public function testDenotedAnswersTheOutcomeOfAnUngroupedNameUse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $input = new TableInput(new QualifiedName(new Name('t')));
        $environment = new Environment($derivation->context, null, [new VisibleRelation($input, (new TableShapes())->fact($derivation, $input->name, $derivation->environment())->shape, null, $input->name)]);
        $facts = new ColumnFacts();
        $resolved = $facts->denoted(new Grouped(new ColumnUse(new Name('b'))), $environment);

        self::assertInstanceOf(ResolvedColumn::class, $resolved);
        self::assertSame($t->declarations()[0]->columns[2], $resolved->slot->column);
        self::assertInstanceOf(MissingColumn::class, $facts->denoted(new ColumnUse(new Name('zz')), $environment));
        self::assertInstanceOf(ResolvedColumn::class, $facts->denoted(new DoubleQuotedWord(new Name('a')), $environment));
        self::assertNull($facts->denoted(new DoubleQuotedWord(new Name('zz')), $environment));
        self::assertNull($facts->denoted(new TruthWord(true), $environment));
        self::assertNull($facts->denoted(new IntegerLiteral('1'), $environment));
    }
}
