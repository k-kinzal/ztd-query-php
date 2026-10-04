<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\QueryRules;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(QueryRules::class)]
#[Medium]
final class QueryRulesTest extends TestCase
{
    public function testStatementLowersASelectOfEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT a AS x FROM db.t AS u WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a x from db.t u where a=1')->toString());
        self::assertSame('SELECT a AS x FROM db.t AS u WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a x from db.t u where a=1')->toString());
        self::assertSame('SELECT a AS x FROM db.t AS u WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a x from db.t u where a=1')->toString());
        self::assertInstanceOf(Select::class, (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('SELECT 1')->statement);
    }

    public function testWhereLowersThePredicateOrNothing(): void
    {
        $filtered = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = 1');
        $plain = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t');

        self::assertInstanceOf(Select::class, $filtered->statement);
        self::assertInstanceOf(Select::class, $plain->statement);
        self::assertInstanceOf(Comparison::class, $filtered->statement->where);
        self::assertNull($plain->statement->where);
    }

    public function testAliasLowersColumnAndTableAliases(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("SELECT a AS `x y`, b 'z' FROM t AS u");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame('x y', $operation->statement->items[0]->alias?->value);
        self::assertSame('z', $operation->statement->items[1]->alias?->value);
        self::assertSame('u', $operation->statement->from?->alias?->value);
        self::assertSame('SELECT a AS `x y`, b AS z FROM t AS u', $operation->toString());
    }

    public function testQueryReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: query');

        $rules->query(new Node('rule', 0, []));
    }

    public function testLegacyQueryReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: legacyQuery');

        $rules->legacyQuery(new Node('rule', 0, []));
    }

    public function testOrderingReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: ordering');

        $rules->ordering(new Node('rule', 0, []));
    }

    public function testOrderItemReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: orderItem');

        $rules->orderItem(new Node('rule', 0, []));
    }

    public function testDirectionReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: direction');

        $rules->direction(new Node('rule', 0, []));
    }

    public function testLimitReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: limit');

        $rules->limit(new Node('rule', 0, []));
    }

    public function testLimitValueReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: limitValue');

        $rules->limitValue(new Node('rule', 0, []));
    }

    public function testTablesReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: tables');

        $rules->tables(new Node('rule', 0, []));
    }

    public function testPartitionsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: partitions');

        $rules->partitions(new Node('rule', 0, []));
    }

    public function testColumnAliasesReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: columnAliases');

        $rules->columnAliases(new Node('rule', 0, []));
    }

    public function testWithReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: with');

        $rules->with(new Node('rule', 0, []));
    }

    public function testSelectItemsReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: selectItems');

        $rules->selectItems(new Node('rule', 0, []));
    }

    public function testIndexNamesReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: indexNames');

        $rules->indexNames(new Node('rule', 0, []));
    }
}
