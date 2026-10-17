<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Having;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingLookup;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Resolution\AggregationScope;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(HavingLookup::class)]
#[Medium]
final class HavingLookupTest extends TestCase
{
    public function testRowPrefersInputColumnsInsideAnOuterAggregateArgument(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE d.t(a INT)');
        $operation = $semantics->analyze('SELECT a AS x FROM d.t', [$table]);
        $relation = $operation->inputRelation();
        self::assertNotNull($relation);
        $visible = new VisibleRelation($relation, $operation->facts->relation($relation)->shape, name: new QualifiedName(new Name('t'), new Name('d')));
        $row = new GroupedRow([$operation->field(0)], [], true);
        $environment = (new HavingScope())->enter(new Environment($operation->context, relations: [$visible]), $row);
        $lookup = new HavingLookup();

        self::assertNull($lookup->row($environment, new Name('a'), null, 1, true));
        self::assertSame($row, $lookup->row($environment, new Name('x'), null, 1, true));
        self::assertSame($row, $lookup->row($environment, new Name('a'), null, 1, false));
        self::assertSame($row, $lookup->row($environment, new Name('a'), null, 0, true));
        self::assertNull($lookup->row(new Environment($operation->context), new Name('a'), null, 1, true));
    }

    public function testMergedExposesOnlyEligibleDerivedInputs(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE d.t(a INT)');
        $merged = $semantics->analyze('SELECT a FROM (SELECT a FROM d.t) x', [$table]);
        $limited = $semantics->analyze('SELECT a FROM (SELECT a FROM d.t LIMIT 1) x', [$table]);
        $column = $merged->field(0)->resolution;
        $materialized = $limited->field(0)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $column);
        self::assertInstanceOf(ResolvedColumn::class, $materialized);
        $environment = new Environment($merged->context);
        $lookup = new HavingLookup();

        self::assertTrue($lookup->merged($environment, [$column]));
        self::assertFalse($lookup->merged($environment, [$materialized]));
        self::assertFalse($lookup->merged($environment, []));
        self::assertFalse($lookup->merged($environment, [$column, $materialized]));
    }

    public function testInputHonorsExplicitSchemaQualification(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE d.t(a INT)');
        $operation = $semantics->analyze('SELECT a FROM d.t', [$table]);
        $relation = $operation->inputRelation();
        self::assertNotNull($relation);
        $visible = new VisibleRelation($relation, $operation->facts->relation($relation)->shape, name: new QualifiedName(new Name('t'), new Name('d')));
        $environment = new Environment($operation->context, relations: [$visible]);
        $lookup = new HavingLookup();

        self::assertCount(1, $lookup->input($environment, new Name('a'), new QualifiedName(new Name('t'), new Name('d')), 1)->found());
        self::assertSame([], $lookup->input($environment, new Name('a'), new QualifiedName(new Name('t'), new Name('other')), 1)->found());
    }

    public function testBlockedRecognizesAggregatesAssignedAfterTheHavingRowWasOpened(): void
    {
        $context = (new Semantics(Dialect::MySql))->context([]);
        $aggregation = new AggregationScope([]);
        $environment = new Environment($context, aggregation: $aggregation);
        $row = new GroupedRow([], [], false);
        $lookup = new HavingLookup();

        self::assertFalse($lookup->blocked($row, $environment, 1));
        self::assertTrue($lookup->blocked($row, $environment, 0));
        $aggregation->register(new Aggregate(AggregateFunction::Count, []));
        self::assertTrue($lookup->blocked($row, $environment, 1));
    }

    public function testBlockedAllowsOuterInputsInMySql91(): void
    {
        $context = (new Semantics(Dialect::MySql, '9.1.0'))->context([]);
        $environment = new Environment($context);
        $row = new GroupedRow([], [], true);

        self::assertFalse((new HavingLookup())->blocked($row, $environment, 1));
        self::assertTrue((new HavingLookup())->blocked($row, $environment, 0));
    }

    public function testMissingNamesTheKnownInputHiddenByAnOuterHavingResult(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE d.t(a INT)');
        $operation = $semantics->analyze('SELECT a FROM d.t', [$table]);
        $relation = $operation->inputRelation();
        self::assertNotNull($relation);
        $visible = new VisibleRelation($relation, $operation->facts->relation($relation)->shape, name: new QualifiedName(new Name('t'), new Name('d')));
        $outer = (new HavingScope())->enter(new Environment($operation->context, relations: [$visible]), new GroupedRow([], [], true));
        $inner = new Environment($operation->context, $outer);
        $lookup = new HavingLookup();
        $missing = $lookup->missing($inner, new Name('a'), null);

        self::assertSame('a', $missing->name->value);
        self::assertSame('d', $missing->qualifier?->schema?->value);
        self::assertSame('t', $missing->qualifier->name->value);
        self::assertNull($lookup->missing($inner, new Name('absent'), null)->qualifier);
        self::assertNull($lookup->missing(new Environment($operation->context), new Name('a'), null)->qualifier);
    }
}
