<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\QueryRules;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Star;

#[CoversClass(QueryRules::class)]
#[Medium]
final class QueryRulesTest extends TestCase
{
    public function testStatementLowersASelectOfEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT a x FROM db.t u WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a x from db.t u where a=1')->toString());
        self::assertSame('SELECT a x FROM db.t u WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a x from db.t u where a=1')->toString());
        self::assertSame('SELECT a x FROM db.t u WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a x from db.t u where a=1')->toString());
    }

    public function testQueryLowersTheQueriesOfOtherStatements(): void
    {
        self::assertSame('SELECT (SELECT 1) AS v, a IN (SELECT 2) AS w FROM (SELECT 3) d WHERE EXISTS (SELECT 4)', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select (select 1) as v, a in (select 2) as w from (select 3) d where exists (select 4)')->toString());
        self::assertSame('SELECT (SELECT 1) AS v FROM t WHERE EXISTS (SELECT 2)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select (select 1) as v from t where exists (select 2)')->toString());
    }

    public function testLegacyQueryLowersCreateSelectWithItsUnion(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('CREATE TABLE t SELECT a FROM u UNION SELECT 2');
        $verb = $tree->children[0];
        self::assertInstanceOf(Node::class, $verb);
        $statement = $verb->children[0];
        self::assertInstanceOf(Node::class, $statement);
        $create = $statement->children[0];
        self::assertInstanceOf(Node::class, $create);
        $second = $create->children[5];
        self::assertInstanceOf(Node::class, $second);
        $third = $second->children[2];
        self::assertInstanceOf(Node::class, $third);
        $select = $third->children[2];
        $union = $third->children[3];
        self::assertInstanceOf(Node::class, $select);
        self::assertInstanceOf(Node::class, $union);

        self::assertInstanceOf(SetOperation::class, (new QueryRules($lowering))->legacyQuery($select, $union));
        self::assertInstanceOf(Select::class, (new QueryRules($lowering))->legacyQuery($select));
    }

    public function testLegacyParenthesizedQueryKeepsTheParenthesesAndTheUnion(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('INSERT INTO t (SELECT a FROM u LIMIT 1) UNION SELECT 2');
        $verb = $tree->children[0];
        self::assertInstanceOf(Node::class, $verb);
        $statement = $verb->children[0];
        self::assertInstanceOf(Node::class, $statement);
        $insert = $statement->children[0];
        self::assertInstanceOf(Node::class, $insert);
        $source = $insert->children[6];
        self::assertInstanceOf(Node::class, $source);
        $expression = $source->children[0];
        self::assertInstanceOf(Node::class, $expression);
        $select = $expression->children[1];
        $union = $expression->children[3];
        self::assertInstanceOf(Node::class, $select);
        self::assertInstanceOf(Node::class, $union);
        $query = (new QueryRules($lowering))->legacyParenthesizedQuery($select, $union);

        self::assertInstanceOf(SetOperation::class, $query);
        self::assertInstanceOf(ParenthesizedQuery::class, $query->left);
        self::assertInstanceOf(Select::class, $query->left->query);
        self::assertNotNull($query->left->query->limit);
    }

    public function testWhereAnswersNullForAnAbsentClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertNull((new QueryRules($lowering))->where(new Node('opt_where_clause', 0, [])));
    }

    public function testOrderingAnswersNothingForAnAbsentClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertSame([], (new QueryRules($lowering))->ordering(new Node('opt_order_clause', 0, [])));
    }

    public function testOrderItemLowersAnOrderingItemOfGroupConcat(): void
    {
        self::assertSame('SELECT GROUP_CONCAT(a ORDER BY a DESC) AS g FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select group_concat(a order by a desc) as g from t')->toString());
    }

    public function testDirectionAnswersNullForNoDirection(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertNull((new QueryRules($lowering))->direction(new Node('opt_ordering_direction', 0, [])));
    }

    public function testLimitAnswersNullForAnAbsentClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertNull((new QueryRules($lowering))->limit(new Node('opt_limit_clause', 0, [])));
    }

    public function testLimitValueLowersAnInteger(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $value = (new QueryRules($lowering))->limitValue(new Node('limit_option', 4, [new Token(0, 'NUM', '7', 0)]));

        self::assertInstanceOf(NumberLiteral::class, $value);
        self::assertSame('7', $value->text);
    }

    public function testTablesLowersTheReferencesOfAMultipleTableStatement(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT 1 FROM t, u');
        $verb = $tree->children[0];
        self::assertInstanceOf(Node::class, $verb);
        $statement = $verb->children[0];
        self::assertInstanceOf(Node::class, $statement);
        $select = $statement->children[0];
        self::assertInstanceOf(Node::class, $select);
        $init = $select->children[0];
        self::assertInstanceOf(Node::class, $init);
        $part = $init->children[1];
        self::assertInstanceOf(Node::class, $part);
        $from = $part->children[2];
        self::assertInstanceOf(Node::class, $from);
        $list = $from->children[1];
        self::assertInstanceOf(Node::class, $list);

        self::assertCount(2, (new QueryRules($lowering))->tables($list));
    }

    public function testPartitionsAnswersNothingForNoSelection(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertSame([], (new QueryRules($lowering))->partitions(new Node('opt_use_partition', 0, [])));
    }

    public function testAliasLowersColumnAndTableAliases(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT a AS `x y`, b 'z' FROM t AS u");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SelectExpression::class, $operation->statement->items[0]);
        self::assertSame('x y', $operation->statement->items[0]->alias?->value);
        self::assertSame('SELECT a AS `x y`, b z FROM t AS u', $operation->toString());
    }

    public function testColumnAliasesAnswersNothingForNoList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertSame([], (new QueryRules($lowering))->columnAliases(new Node('opt_derived_column_list', 0, [])));
    }

    public function testWithAnswersNullForNoClause(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertNull((new QueryRules($lowering))->with(new Node('opt_with_clause', 0, [])));
    }

    public function testSelectItemsLowersTheStar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $items = (new QueryRules($lowering))->selectItems(new Node('select_item_list', 2, [new Token(0, '*', '*', 0)]));

        self::assertCount(1, $items);
        self::assertInstanceOf(Star::class, $items[0]);
    }

    public function testIndexNamesAnswersNothingForNoList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertSame([], (new QueryRules($lowering))->indexNames(new Node('opt_key_usage_list', 0, [])));
    }

    public function testIndexKeysAnswersNothingForNoList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        self::assertSame([], (new QueryRules($lowering))->indexKeys(new Node('opt_key_usage_list', 0, [])));
    }

    public function testMarkAnswersWhatIsWrittenBeforeAColumnOrTableAlias(): void
    {
        self::assertSame('SELECT a x, b AS y FROM t u', (new Semantics(Dialect::MySql))->analyze('select a x, b as y from t u')->toString());
        self::assertSame('SELECT f(a b, c AS d) AS e FROM t', (new Semantics(Dialect::MySql))->analyze('select f(a b, c as d) as e from t')->toString());
    }
}
