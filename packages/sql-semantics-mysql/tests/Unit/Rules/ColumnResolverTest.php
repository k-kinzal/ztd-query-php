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
    public function testMissingDistinguishesTablelessQualifiersFromMissingColumns(): void
    {
        $legacy = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $modern = new Semantics(Dialect::MySql);
        $qualifier = new QualifiedName(new Name('t'));
        $resolver = new ColumnResolver();

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier::class, $resolver->missing(new Environment($legacy->context()), new Name('a'), $qualifier));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Name\MissingTableQualifier::class, $resolver->missing(new Environment($modern->context()), new Name('a'), $qualifier));
        self::assertInstanceOf(MissingColumn::class, $resolver->missing(new Environment($legacy->context()), new Name('a'), null));
        self::assertInstanceOf(MissingColumn::class, $legacy->analyze('SELECT (SELECT t.a)')->diagnostics()[0]);
    }

    public function testOutputRetainsAliasDepthAndLeavesUnlistedNamesUnresolved(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x');
        $scope = new Environment((new Semantics(Dialect::MySql))->context([]), aliases: [$operation->field('x')]);
        $reference = (new ColumnResolver())->output($scope, new Name('x'), 2, []);

        self::assertInstanceOf(AliasTarget::class, $reference);
        self::assertSame(2, $reference->depth);
        self::assertNull((new ColumnResolver())->output($scope, new Name('y'), 2, []));
    }

    public function testFindPrefersAColumnOfTheFromClauseToAnAlias(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('SELECT b AS a FROM t GROUP BY a HAVING a > 0', [$table]);
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $group->items[0]->expression->scalarInformation()->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->columns[0], $resolution->declaration());
        self::assertSame(["Expression #1 of SELECT list is not in GROUP BY clause and contains nonaggregated column '(current).t.b' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    public function testFindFallsBackToTheAliasOfASelectListItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x FROM t GROUP BY x ORDER BY x + 1', [$table]);
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $group->items[0]->expression->scalarInformation()->resolution;

        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame($operation->field('x'), $resolution->field);
        self::assertSame([], $operation->diagnostics());
    }

    public function testFindReportsAnAliasOfSeveralDifferentItems(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x, b AS x FROM t GROUP BY x', [$table]);

        self::assertCount(1, $operation->diagnostics());
        self::assertInstanceOf(AmbiguousAlias::class, $operation->diagnostics()[0]);
        self::assertSame('x', $operation->diagnostics()[0]->name->value);
    }

    public function testFindIsConditionalWhileAnUndeclaredRelationCouldOwnTheName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x FROM u GROUP BY x');
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $group->items[0]->expression->scalarInformation()->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
    }

    public function testFindIgnoresAliasesForAQualifiedName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x FROM t', [$table]);

        self::assertInstanceOf(MissingColumn::class, (new ColumnResolver())->find(new Environment($semantics->context([$table]), null, [], [], [$operation->field('x')]), new Name('x'), new QualifiedName(new Name('t'))));
        self::assertInstanceOf(AliasTarget::class, (new ColumnResolver())->find(new Environment($semantics->context([$table]), null, [], [], [$operation->field('x')]), new Name('X')));
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
        $outer = (new ColumnResolver())->find(new Environment((new Semantics(Dialect::MySql))->context([]), new Environment((new Semantics(Dialect::MySql))->context([]), aliases: [$fields[0]])), new Name('x'));
        self::assertInstanceOf(AliasTarget::class, $outer);
        self::assertSame(1, $outer->depth);
    }

    public function testUnlistedAnswersTheOccurrencesThatMayGiveAHavingPositionTheName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $star = $semantics->analyze('SELECT * FROM u HAVING c > 0');
        $grouped = $semantics->analyze('SELECT 1 FROM u GROUP BY c HAVING c > 0');
        $other = $semantics->analyze('SELECT 1 FROM u GROUP BY d HAVING c > 0');
        $starSelect = $star;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $starSelect);
        $starSelect = $starSelect->query;
        $groupedSelect = $grouped;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $groupedSelect);
        $groupedSelect = $groupedSelect->query;
        self::assertInstanceOf(Select::class, $starSelect);
        self::assertInstanceOf(Select::class, $groupedSelect);
        self::assertInstanceOf(Comparison::class, $starSelect->having);
        self::assertInstanceOf(Comparison::class, $groupedSelect->having);
        $starred = $starSelect->having->left->scalarInformation()->resolution;
        $group = $groupedSelect->having->left->scalarInformation()->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $starred);
        self::assertSame([$starSelect->from], $starred->relations);
        self::assertInstanceOf(ConditionalColumn::class, $group);
        self::assertSame([$groupedSelect->from], $group->relations);
        self::assertCount(1, $other->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $other->diagnostics()[0]);
        self::assertSame([], (new ColumnResolver())->unlisted(new GroupedRow([], [], true), new Environment($semantics->context([])), new Name('c')));
    }

    public function testFactAnswersTheFactsOfTheColumnOrTheProblem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT NOT NULL)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT NOT NULL)')]), null, [], [], [$operation->field('a')]);

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
        $visible = new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')]), null, [new VisibleRelation($resolution->relation, $resolution->relation->relationInformation()->shape, new Name('NEW'))]);

        self::assertTrue((new ColumnResolver())->row(new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')]), $visible), new QualifiedName(new Name('new'))));
        self::assertFalse((new ColumnResolver())->row($visible, new QualifiedName(new Name('OLD'))));
        self::assertFalse((new ColumnResolver())->row($visible, new QualifiedName(new Name('NEW'), new Name('db'))));
        self::assertFalse((new ColumnResolver())->row(new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')])), new QualifiedName(new Name('NEW'))));
    }

    public function testFindIsConditionalWhileAnItemWithoutADecidedNameCouldHaveTheName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze("SELECT 'é' FROM t GROUP BY q", [$table]);
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $group->items[0]->expression->scalarInformation()->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertEquals([new SessionState('character_set_client')], $resolution->missing);
        self::assertSame([], $operation->diagnostics());
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
        $resolution = (new ColumnResolver())->undecided(new Name('a'), [], [new VisibleRelation($input, $input->relationInformation()->shape)], [new SessionState('character_set_client')]);

        self::assertSame([$input], $resolution->relations);
        self::assertCount(2, $resolution->missing);
    }

    public function testFindRefusesAQualifierThatNamesAnotherDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql, null, null, \SqlSemantics\Contract\ParameterStyle::Native, new \SqlSemantics\Contract\SearchPath('d'));
        $t = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertSame([0, 1], [count($semantics->analyze('SELECT d.t.a FROM t', [$t])->diagnostics()), count($semantics->analyze('SELECT a FROM t WHERE e.t.a = 1', [$t])->diagnostics())]);
    }
}
