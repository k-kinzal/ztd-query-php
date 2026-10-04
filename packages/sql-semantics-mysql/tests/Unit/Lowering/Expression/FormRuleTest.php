<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\FormRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\JsonExtraction;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;

#[CoversClass(FormRule::class)]
#[Medium]
final class FormRuleTest extends TestCase
{
    public function testLowerLowersTheBracketedAndColumnForms(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new FormRule($lowering);
        $productions = $platform->productions($profile);
        $parser = $platform->parser($profile);

        self::assertInstanceOf(Grouped::class, $rule->lower($productions->form($parser->parse('SELECT (1)')->find('simple_expr')[0])));
        self::assertInstanceOf(Row::class, $rule->lower($productions->form($parser->parse('SELECT ROW(1, 2)')->find('simple_expr')[0])));
        self::assertInstanceOf(ScalarSubquery::class, $rule->lower($productions->form($parser->parse('SELECT (SELECT 1)')->find('simple_expr')[0])));
        self::assertInstanceOf(Exists::class, $rule->lower($productions->form($parser->parse('SELECT EXISTS (SELECT 1)')->find('simple_expr')[0])));
        self::assertInstanceOf(OdbcEscape::class, $rule->lower($productions->form($parser->parse("SELECT { d '2024-01-01' }")->find('simple_expr')[0])));
        self::assertInstanceOf(DefaultOfColumn::class, $rule->lower($productions->form($parser->parse('SELECT DEFAULT(a)')->find('simple_expr')[0])));
        self::assertInstanceOf(InsertedColumn::class, $rule->lower($productions->form($parser->parse('SELECT VALUES(a)')->find('simple_expr')[0])));
        self::assertInstanceOf(IntervalAddition::class, $rule->lower($productions->form($parser->parse('SELECT INTERVAL 1 DAY + a')->find('simple_expr')[0])));
        self::assertInstanceOf(JsonExtraction::class, $rule->lower($productions->form($parser->parse("SELECT a->'$'")->find('simple_expr')[0])));
    }

    public function testLowerLowersTheSubqueryFormsOfMySql56(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new FormRule($lowering);

        self::assertInstanceOf(ScalarSubquery::class, $rule->lower($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT (SELECT 1)')->find('simple_expr')[0])));
        self::assertInstanceOf(Exists::class, $rule->lower($platform->productions($profile)->form($platform->parser($profile)->parse('SELECT EXISTS (SELECT 1)')->find('simple_expr')[0])));
    }
}
