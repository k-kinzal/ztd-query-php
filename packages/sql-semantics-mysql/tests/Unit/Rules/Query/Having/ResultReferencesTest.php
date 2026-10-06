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
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->having);
        $resolution = $operation->facts->scalar($select->having->left)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame('a', $resolution->slot->name?->value);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testGroupingSkipsAColumnOfAnotherOccurrenceAndReportsTwoDifferentColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL)');
        $other = $semantics->analyze('SELECT u.a AS a FROM t, t AS u GROUP BY t.a HAVING u.a > 0', [$table]);
        $ambiguous = $semantics->analyze('SELECT 1 FROM t, t AS u GROUP BY t.a, u.a HAVING a > 0', [$table]);
        $select = $other->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->having);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $resolution = $other->facts->scalar($select->having->left)->resolution;
        $selected = $other->facts->scalar($item->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertInstanceOf(ResolvedColumn::class, $selected);

        self::assertSame($selected->relation, $resolution->relation);
        self::assertSame([], $other->facts->diagnostics);
        self::assertCount(1, $ambiguous->facts->diagnostics);
        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous->facts->diagnostics[0]);
    }

    public function testSelectedResolvesAColumnItemToItsColumnAndAnotherItemToTheItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL, b INT)');
        $column = $semantics->analyze('SELECT a AS x FROM t HAVING x > 0', [$table]);
        $computed = $semantics->analyze('SELECT a + 1 AS x FROM t HAVING x > 0', [$table]);
        $hidden = $semantics->analyze('SELECT t.a AS x FROM t HAVING a > 0', [$table]);
        $ambiguous = $semantics->analyze('SELECT a, b AS a FROM t HAVING a > 0', [$table]);
        $columnSelect = $column->statement;
        $computedSelect = $computed->statement;
        $hiddenSelect = $hidden->statement;
        self::assertInstanceOf(Select::class, $columnSelect);
        self::assertInstanceOf(Select::class, $computedSelect);
        self::assertInstanceOf(Select::class, $hiddenSelect);
        self::assertInstanceOf(Comparison::class, $columnSelect->having);
        self::assertInstanceOf(Comparison::class, $computedSelect->having);
        self::assertInstanceOf(Comparison::class, $hiddenSelect->having);
        $alias = $computed->facts->scalar($computedSelect->having->left)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $column->facts->scalar($columnSelect->having->left)->resolution);
        self::assertInstanceOf(AliasTarget::class, $alias);
        self::assertSame($computed->field('x'), $alias->field);
        self::assertInstanceOf(ResolvedColumn::class, $hidden->facts->scalar($hiddenSelect->having->left)->resolution);
        self::assertCount(1, $ambiguous->facts->diagnostics);
        self::assertInstanceOf(AmbiguousColumn::class, $ambiguous->facts->diagnostics[0]);
    }

    public function testNamedComparesTheColumnNameByTheColumnComparisonOfTheContext(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($operation->context);

        self::assertTrue((new ResultReferences())->named($environment, $resolution, new Name('A')));
        self::assertFalse((new ResultReferences())->named($environment, $resolution, new Name('b')));
    }

    public function testAdmittedTellsWhetherTheQualifierFindsTheColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('a')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($operation->context, null, [new VisibleRelation($resolution->relation, $operation->facts->relation($resolution->relation)->shape, null, new QualifiedName(new Name('t')))]);

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
        self::assertNull((new ResultReferences())->find(new GroupedRow([], [], true), new Environment($operation->context), new Name('a'), null, 0));
    }

    public function testUnaliasedMatchesAColumnItemByItsColumnInAnAdmittedOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze('SELECT a AS x FROM t', [$semantics->analyze('CREATE TABLE t (a INT)')]);
        $resolution = $operation->field('x')->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $environment = new Environment($operation->context, null, [new VisibleRelation($resolution->relation, $operation->facts->relation($resolution->relation)->shape, null, new QualifiedName(new Name('t')))]);

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
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $having = $select->having;
        self::assertInstanceOf(Logical::class, $having);
        self::assertInstanceOf(Comparison::class, $having->left);
        self::assertInstanceOf(Comparison::class, $having->right);
        $q = $operation->facts->scalar($having->left->left)->resolution;
        $r = $operation->facts->scalar($having->right->left)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $q);
        self::assertCount(1, $q->candidates);
        self::assertInstanceOf(ConditionalColumn::class, $r);
        self::assertSame([], $r->candidates);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testUnnamedStopsAtTheItemTheNameNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze("SELECT 'é', 1 AS r, 'ü' FROM DUAL HAVING r > 0");
        $output = $operation->facts->output;
        self::assertNotNull($output);
        $row = new GroupedRow($output->projection, [], false);
        $scope = new Environment($operation->context);

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
