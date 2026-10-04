<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Identifier\Name;
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
}
