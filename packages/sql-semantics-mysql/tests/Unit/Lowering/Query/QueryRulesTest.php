<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\QueryRules;
use SqlSemantics\Platform\MySql\Platform;

#[CoversClass(QueryRules::class)]
#[Small]
final class QueryRulesTest extends TestCase
{
    public function testStatementReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: statement');

        $rules->statement(new Node('rule', 0, []));
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

    public function testWhereReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: where');

        $rules->where(new Node('rule', 0, []));
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

    public function testAliasReportsTheMissingRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rules = new QueryRules(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: MySQL query family: alias');

        $rules->alias(new Node('rule', 0, []));
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
