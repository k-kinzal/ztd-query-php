<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\ColumnResolver;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnResolver::class)]
#[Medium]
final class ColumnResolverTest extends TestCase
{
    public function testFindPrefersAColumnOfTheFromClauseToAnAlias(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('SELECT b AS a FROM t GROUP BY a HAVING a > 0', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->columns[0], $resolution->declaration());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testFindFallsBackToTheAliasOfASelectListItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x FROM t GROUP BY x ORDER BY x + 1', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame($operation->field('x'), $resolution->field);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testFindReportsAnAliasOfSeveralDifferentItems(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x, b AS x FROM t GROUP BY x', [$table]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(AmbiguousAlias::class, $operation->facts->diagnostics[0]);
        self::assertSame('x', $operation->facts->diagnostics[0]->name->value);
    }

    public function testFindIsConditionalWhileAnUndeclaredRelationCouldOwnTheName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x FROM u GROUP BY x');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
    }

    public function testFindIgnoresAliasesForAQualifiedName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x FROM t', [$table]);

        self::assertInstanceOf(MissingColumn::class, (new ColumnResolver())->find(new Environment($operation->context, null, [], [], [$operation->field('x')]), new Name('x'), new QualifiedName(new Name('t'))));
        self::assertInstanceOf(AliasTarget::class, (new ColumnResolver())->find(new Environment($operation->context, null, [], [], [$operation->field('x')]), new Name('X')));
    }

    public function testAliasTreatsItemsComputingTheSameExpressionAsOne(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x, 1 AS x, 2 AS x', []);
        $fields = $operation->fields()->items ?? [];

        self::assertInstanceOf(AliasTarget::class, (new ColumnResolver())->alias(new Name('x'), [$fields[0], $fields[1]]));
        $single = (new ColumnResolver())->alias(new Name('x'), [$fields[2]]);
        self::assertInstanceOf(AliasTarget::class, $single);
        self::assertSame($fields[2], $single->field);
        $ambiguous = (new ColumnResolver())->alias(new Name('x'), [$fields[0], $fields[1], $fields[2]]);
        self::assertInstanceOf(AmbiguousAlias::class, $ambiguous);
        self::assertCount(3, $ambiguous->candidates);
    }

    public function testUnlistedAnswersTheOccurrencesThatMayGiveAHavingPositionTheName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $star = $semantics->analyze('SELECT * FROM u HAVING c > 0');
        $grouped = $semantics->analyze('SELECT 1 FROM u GROUP BY c HAVING c > 0');
        $other = $semantics->analyze('SELECT 1 FROM u GROUP BY d HAVING c > 0');
        $starSelect = $star->statement;
        $groupedSelect = $grouped->statement;
        self::assertInstanceOf(Select::class, $starSelect);
        self::assertInstanceOf(Select::class, $groupedSelect);
        self::assertInstanceOf(Comparison::class, $starSelect->having);
        self::assertInstanceOf(Comparison::class, $groupedSelect->having);
        $starred = $star->facts->scalar($starSelect->having->left)->resolution;
        $group = $grouped->facts->scalar($groupedSelect->having->left)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $starred);
        self::assertSame([$starSelect->from], $starred->relations);
        self::assertInstanceOf(ConditionalColumn::class, $group);
        self::assertSame([$groupedSelect->from], $group->relations);
        self::assertCount(1, $other->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $other->facts->diagnostics[0]);
        self::assertSame([], (new ColumnResolver())->unlisted(new GroupedRow([], [], true), new Environment($star->context), new Name('c')));
    }

    public function testFactAnswersTheFactsOfTheColumnOrTheProblem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT NOT NULL)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($operation->context, null, [], [], [$operation->field('a')]);

        self::assertSame(Nullability::NotNull, (new ColumnResolver())->fact($environment, new Name('a'))->nullability);
        self::assertInstanceOf(AliasTarget::class, (new ColumnResolver())->fact($environment, new Name('a'))->resolution);
        self::assertInstanceOf(MissingColumn::class, (new ColumnResolver())->fact($environment, new Name('b'))->resolution);
        self::assertInstanceOf(Invalid::class, (new ColumnResolver())->fact($environment, new Name('b'))->type);
    }

    public function testRowTellsWhetherATriggerRowIsVisible(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT new.a FROM t AS new', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $visible = new Environment($operation->context, null, [new VisibleRelation($resolution->relation, $operation->facts->relation($resolution->relation)->shape, new Name('NEW'))]);

        self::assertTrue((new ColumnResolver())->row(new Environment($operation->context, $visible), new QualifiedName(new Name('new'))));
        self::assertFalse((new ColumnResolver())->row($visible, new QualifiedName(new Name('OLD'))));
        self::assertFalse((new ColumnResolver())->row($visible, new QualifiedName(new Name('NEW'), new Name('db'))));
        self::assertFalse((new ColumnResolver())->row(new Environment($operation->context), new QualifiedName(new Name('NEW'))));
    }

    public function testFindIsConditionalWhileAnItemWithoutADecidedNameCouldHaveTheName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze("SELECT 'é' FROM t GROUP BY q", [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertEquals([new SessionState('character_set_client')], $resolution->missing);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testUnnamedAnswersTheInputsTheItemNamesDependOn(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT 'é' AS x, 'ü'");

        self::assertEquals([new SessionState('character_set_client')], (new ColumnResolver())->unnamed([$operation->field(0), $operation->field(1)]));
    }

    public function testUndecidedJoinsTheInputsOfOpenOccurrencesAndUnnamedItems(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM u');
        $input = $operation->inputRelation();
        self::assertNotNull($input);
        $resolution = (new ColumnResolver())->undecided(new Name('a'), [], [new VisibleRelation($input, $operation->facts->relation($input)->shape)], [new SessionState('character_set_client')]);

        self::assertSame([$input], $resolution->relations);
        self::assertCount(2, $resolution->missing);
    }
}
