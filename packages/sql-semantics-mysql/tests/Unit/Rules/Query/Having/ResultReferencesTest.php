<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Having;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Having\GroupedRow;
use SqlSemantics\Platform\MySql\Rules\Query\Having\ResultReferences;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\SessionState;

#[CoversClass(ResultReferences::class)]
#[Medium]
final class ResultReferencesTest extends TestCase
{
    public function testFindPrefersAGroupByColumnToASelectListItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL, b INT)');
        $operation = $semantics->analyze('SELECT b AS a FROM t GROUP BY a HAVING a > 0', [$table]);
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->having);
        $resolution = $select->having->left->scalarInformation()->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame('a', $resolution->slot->name?->value);
        self::assertCount(1, $operation->diagnostics());
        self::assertInstanceOf(NonGroupedColumn::class, $operation->diagnostics()[0]);
    }

    public function testGroupingSkipsAColumnOfAnotherOccurrenceAndReportsTwoDifferentColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL)');
        $other = $semantics->analyze('SELECT u.a AS a FROM t, t AS u GROUP BY t.a HAVING u.a > 0', [$table]);
        $ambiguous = $semantics->analyze('SELECT 1 FROM t, t AS u GROUP BY t.a, u.a HAVING a > 0', [$table]);
        $select = $other;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->having);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $resolution = $select->having->left->scalarInformation()->resolution;
        $selected = $item->expression->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertInstanceOf(ResolvedColumn::class, $selected);

        self::assertSame($selected->relation, $resolution->relation);
        self::assertInstanceOf(NonGroupedColumn::class, $other->diagnostics()[0]);
        self::assertCount(1, $ambiguous->diagnostics());
        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous->diagnostics()[0]);
    }

    public function testSelectedResolvesAColumnItemToItsColumnAndAnotherItemToTheItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL, b INT)');
        $column = $semantics->analyze('SELECT a AS x FROM t HAVING x > 0', [$table]);
        $computed = $semantics->analyze('SELECT a + 1 AS x FROM t HAVING x > 0', [$table]);
        $hidden = $semantics->analyze('SELECT t.a AS x FROM t HAVING a > 0', [$table]);
        $ambiguous = $semantics->analyze('SELECT a, b AS a FROM t HAVING a > 0', [$table]);
        $columnSelect = $column;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $columnSelect);
        $columnSelect = $columnSelect->query;
        $computedSelect = $computed;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $computedSelect);
        $computedSelect = $computedSelect->query;
        $hiddenSelect = $hidden;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $hiddenSelect);
        $hiddenSelect = $hiddenSelect->query;
        self::assertInstanceOf(Select::class, $columnSelect);
        self::assertInstanceOf(Select::class, $computedSelect);
        self::assertInstanceOf(Select::class, $hiddenSelect);
        self::assertInstanceOf(Comparison::class, $columnSelect->having);
        self::assertInstanceOf(Comparison::class, $computedSelect->having);
        self::assertInstanceOf(Comparison::class, $hiddenSelect->having);
        $alias = $computedSelect->having->left->scalarInformation()->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $columnSelect->having->left->scalarInformation()->resolution);
        self::assertInstanceOf(AliasTarget::class, $alias);
        self::assertSame($computed->field('x'), $alias->field);
        $outer = (new ResultReferences())->selected(new GroupedRow([$computed->field('x')], [], false), new Environment($semantics->context([$table])), new Name('x'), null, 2);
        self::assertInstanceOf(AliasTarget::class, $outer);
        self::assertSame(2, $outer->depth);
        self::assertInstanceOf(ResolvedColumn::class, $hiddenSelect->having->left->scalarInformation()->resolution);
        self::assertCount(1, $ambiguous->diagnostics());
        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous->diagnostics()[0]);
    }

    public function testNamedComparesTheColumnNameByTheColumnComparisonOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')]));

        self::assertTrue((new ResultReferences())->named($environment, $resolution, new Name('A')));
        self::assertFalse((new ResultReferences())->named($environment, $resolution, new Name('b')));
    }

    public function testAdmittedTellsWhetherTheQualifierFindsTheColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')]), null, [new VisibleRelation($resolution->relation, $resolution->relation->relationInformation()->shape, null, new QualifiedName(new Name('t')))]);

        self::assertTrue((new ResultReferences())->admitted($environment, $resolution, new Name('a'), new QualifiedName(new Name('t'))));
        self::assertFalse((new ResultReferences())->admitted($environment, $resolution, new Name('a'), new QualifiedName(new Name('u'))));
    }

    public function testSameComparesTheOccurrenceAndTheSlot(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a, b FROM t', [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);
        $first = $operation->field('a')->resolution;
        $second = $operation->field('b')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        self::assertInstanceOf(ResolvedColumn::class, $second);

        self::assertTrue((new ResultReferences())->same($first, new ResolvedColumn($first->relation, $first->slot, 2)));
        self::assertFalse((new ResultReferences())->same($first, $second));
    }

    public function testDeeperCountsTheQueriesBetweenTheUseAndTheColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);

        self::assertSame($resolution, (new ResultReferences())->deeper($resolution, 0));
        self::assertSame($resolution->depth + 2, (new ResultReferences())->deeper($resolution, 2)->depth);
        self::assertSame($resolution->slot, (new ResultReferences())->deeper($resolution, 2)->slot);
        self::assertTrue((new ResultReferences())->deeper($resolution, 2)->resultReference);
        self::assertNull((new ResultReferences())->find(new GroupedRow([], [], true), new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')])), new Name('a'), null, 0));
    }

    public function testUnaliasedMatchesAColumnItemByItsColumnInAnAdmittedOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a AS x FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('x')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($semantics->context([$semantics->analyze('CREATE TABLE t (a INT)')]), null, [new VisibleRelation($resolution->relation, $resolution->relation->relationInformation()->shape, null, new QualifiedName(new Name('t')))]);

        self::assertTrue((new ResultReferences())->unaliased($environment, $resolution, new Name('a'), null));
        self::assertTrue((new ResultReferences())->unaliased($environment, $resolution, new Name('a'), new QualifiedName(new Name('t'))));
        self::assertFalse((new ResultReferences())->unaliased($environment, $resolution, new Name('a'), new QualifiedName(new Name('u'))));
        self::assertFalse((new ResultReferences())->unaliased($environment, $resolution, new Name('x'), null));
    }

    public function testChosenPrefersTheAliasedMatchAndReportsTwoDifferentUnaliasedOnes(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a, b FROM t', [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);
        $first = $operation->field('a')->resolution;
        $second = $operation->field('b')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        self::assertInstanceOf(ResolvedColumn::class, $second);

        self::assertSame($first, (new ResultReferences())->chosen(new Name('a'), $first, $second, $second, 0));
        self::assertSame($second, (new ResultReferences())->chosen(new Name('a'), null, $second, null, 0));
        self::assertInstanceOf(AmbiguousColumn::class, (new ResultReferences())->chosen(new Name('a'), null, $first, $second, 0));
        self::assertNull((new ResultReferences())->chosen(new Name('a'), null, null, null, 0));
        $deeper = (new ResultReferences())->chosen(new Name('a'), $first, null, null, 1);
        self::assertInstanceOf(ResolvedColumn::class, $deeper);
        self::assertSame($first->depth + 1, $deeper->depth);
    }

    public function testSelectedIsConditionalWhileAnItemWithoutADecidedNameCouldHaveTheName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, q INT)')->declarations();
        $operation = $semantics->analyze("SELECT 'é', a AS q, 1 AS r FROM t HAVING q > 0 AND r > 0", $table);
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        $having = $select->having;
        self::assertInstanceOf(Logical::class, $having);
        self::assertInstanceOf(Comparison::class, $having->left);
        self::assertInstanceOf(Comparison::class, $having->right);
        $q = $having->left->left->scalarInformation()->resolution;
        $r = $having->right->left->scalarInformation()->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $q);
        self::assertCount(1, $q->candidates);
        self::assertInstanceOf(ConditionalColumn::class, $r);
        self::assertSame([], $r->candidates);
        self::assertSame([], $operation->diagnostics());
    }

    public function testUnnamedStopsAtTheItemTheNameNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze("SELECT 'é', 1 AS r, 'ü' FROM DUAL HAVING r > 0");
        $output = $operation->output();
        self::assertNotNull($output);
        $row = new GroupedRow($output->projection, [], false);
        $scope = new Environment($semantics->context([]));

        self::assertCount(1, (new ResultReferences())->unnamed($row, $scope, new Name('r'), null));
        self::assertCount(2, (new ResultReferences())->unnamed($row, $scope, new Name('s'), null));
        self::assertSame([], (new ResultReferences())->unnamed($row, $scope, new Name('s'), new QualifiedName(new Name('t'))));
    }

    public function testUndecidedKeepsAResolvedMatchAsCandidate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT 1 AS r FROM DUAL');
        $alias = new AliasTarget($operation->field(0));
        $missing = [new SessionState('character_set_client')];

        self::assertSame($alias, (new ResultReferences())->undecided(new Name('r'), $alias, []));
        self::assertInstanceOf(ConditionalColumn::class, (new ResultReferences())->undecided(new Name('r'), $alias, $missing));
        self::assertNull((new ResultReferences())->undecided(new Name('r'), null, []));
    }
}
