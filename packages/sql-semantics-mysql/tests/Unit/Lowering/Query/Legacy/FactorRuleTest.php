<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\SyntaxException;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\FactorRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;

#[CoversClass(FactorRule::class)]
#[Medium]
final class FactorRuleTest extends TestCase
{
    public function testFactorRejectsASelectOutsideParentheses(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM t, SELECT 1');
    }

    public function testParensLowersDerivedTablesAndNestedJoins(): void
    {
        self::assertSame('SELECT 1 FROM (SELECT 1) d, (t, u), ((SELECT 2)) e', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from (select 1) d, (t, u), ((select 2)) e')->toString());
        self::assertSame('SELECT 1 FROM (SELECT 1 UNION SELECT 2 ORDER BY 1) d', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from (select 1 union select 2 order by 1) d')->toString());
    }

    public function testParensRejectsAnAliasOnANestedJoin(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 1 FROM (t, u) AS x');
    }

    public function testContentAnswersTheBlockOfADerivedTable(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM ((SELECT 1)) AS d');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(DerivedTable::class, $operation->statement->from);
        self::assertInstanceOf(ParenthesizedQuery::class, $operation->statement->from->query);
    }

    public function testSingleAnswersOnlyALoneParenthesizedOrSelectFactor(): void
    {
        self::assertSame('SELECT 1 FROM ((t))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from ((t))')->toString());
    }

    public function testRefusalPlacesTheSyntaxErrorOfANestedJoinWhereEachReleaseReportsIt(): void
    {
        $platform = new Platform();
        $legacy = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $modern = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $tree = $platform->parser($modern)->parse('SELECT 1 FROM (t, u) x');
        $factor = $tree->find('table_factor')[0];
        $aliases = $tree->find('opt_table_alias');
        $alias = $aliases[count($aliases) - 1];
        $closing = $factor->children[2];
        $ordering = $tree->find('opt_union_order_or_limit')[0];
        $old = (new FactorRule(new Lowering($platform->productions($legacy), new Leaves(), $legacy)))->refusal($closing, null, $ordering, $alias, false, false)->getPrevious();
        $new = (new FactorRule(new Lowering($platform->productions($modern), new Leaves(), $modern)))->refusal($closing, null, $ordering, $alias, false, false)->getPrevious();

        self::assertInstanceOf(SyntaxException::class, $old);
        self::assertInstanceOf(SyntaxException::class, $new);
        self::assertSame([21, 20], [$old->token->offset, $new->token->offset]);
    }

    public function testRefusalPlacesTheSyntaxErrorOfAUnionAfterANestedJoinAtTheSecondUnionInMySql56(): void
    {
        $platform = new Platform();
        $legacy = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $tree = $platform->parser($legacy)->parse('SELECT 1 FROM (t UNION SELECT 1 UNION SELECT 2)');
        $unions = $tree->find('select_derived_union');
        $factor = $tree->find('table_factor')[0];
        $aliases = $tree->find('opt_table_alias');
        $refusal = (new FactorRule(new Lowering($platform->productions($legacy), new Leaves(), $legacy)))->refusal($factor->children[3], $unions[1]->children[1], $tree->find('opt_union_order_or_limit')[0], $aliases[count($aliases) - 1], true, false, $unions[0]->children[1])->getPrevious();

        self::assertInstanceOf(SyntaxException::class, $refusal);
        self::assertSame(32, $refusal->token->offset);
    }

    public function testRefusalPlacesTheSyntaxErrorOfAnOrderedNestedJoinBeforeItsUnionInMySql57(): void
    {
        $platform = new Platform();
        $modern = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $tree = $platform->parser($modern)->parse('SELECT 1 FROM (t ORDER BY 1 UNION SELECT 2)');
        $unions = $tree->find('select_derived_union');
        $factor = $tree->find('table_factor')[0];
        $aliases = $tree->find('opt_table_alias');
        $refusal = (new FactorRule(new Lowering($platform->productions($modern), new Leaves(), $modern)))->refusal($factor->children[3], $unions[0]->children[1], $tree->find('opt_union_order_or_limit')[0], $aliases[count($aliases) - 1], true, true)->getPrevious();

        self::assertInstanceOf(SyntaxException::class, $refusal);
        self::assertSame(17, $refusal->token->offset);
    }

    public function testRefusalPlacesTheSyntaxErrorOfALimitOfTwoValuesAtTheLastValueInMySql56(): void
    {
        $platform = new Platform();
        $legacy = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $tree = $platform->parser($legacy)->parse('SELECT 1 FROM (t LIMIT 1, 2)');
        $factor = $tree->find('table_factor')[0];
        $aliases = $tree->find('opt_table_alias');
        $refusal = (new FactorRule(new Lowering($platform->productions($legacy), new Leaves(), $legacy)))->refusal($factor->children[3], null, $tree->find('opt_union_order_or_limit')[0], $aliases[count($aliases) - 1], false, true)->getPrevious();

        self::assertInstanceOf(SyntaxException::class, $refusal);
        self::assertSame(26, $refusal->token->offset);
    }

    public function testLeadingPlacesTheSyntaxErrorOfASelectWithoutAliasStartingANestedJoin(): void
    {
        $platform = new Platform();
        $legacy = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $modern = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $text = 'SELECT 1 FROM ((SELECT 1) JOIN t ON 1)';
        $old = $platform->parser($legacy)->parse($text);
        $new = $platform->parser($modern)->parse($text);
        $first = (new FactorRule(new Lowering($platform->productions($legacy), new Leaves(), $legacy)))->leading($old->find('select_derived')[0], $old->find('table_factor')[0]->children[3])?->getPrevious();
        $second = (new FactorRule(new Lowering($platform->productions($modern), new Leaves(), $modern)))->leading($new->find('select_derived')[0], $new->find('table_factor')[0]->children[3])?->getPrevious();

        self::assertInstanceOf(SyntaxException::class, $first);
        self::assertInstanceOf(SyntaxException::class, $second);
        self::assertSame([33, 26], [$first->token->offset, $second->token->offset]);
    }
}
