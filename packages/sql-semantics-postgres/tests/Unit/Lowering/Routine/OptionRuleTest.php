<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\OptionRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute;

#[CoversClass(OptionRule::class)]
#[Small]
final class OptionRuleTest extends TestCase
{
    public function testOptionsLowersAnEmptyList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int RETURN 1');
        self::assertSame([], (new OptionRule($lowering))->options($tree->find('opt_createfunc_opt_list')[0]));
    }

    public function testOptionLowersWindowAndLanguage(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int WINDOW LANGUAGE c AS $$x$$');
        $rule = new OptionRule($lowering);
        self::assertSame(RoutineAttribute::Window, $rule->option($tree->find('createfunc_opt_item')[0]));
        self::assertSame('language', $rule->option($tree->find('createfunc_opt_item')[1])->setting());
    }

    public function testCommonLowersExternalSecurityAsSecurity(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER FUNCTION f() EXTERNAL SECURITY DEFINER COST 5');
        $rule = new OptionRule($lowering);
        self::assertSame(RoutineAttribute::SecurityDefiner, $rule->common($tree->find('common_func_opt_item')[0]));
        self::assertSame('cost', $rule->common($tree->find('common_func_opt_item')[1])->setting());
    }

    public function testDefinitionLowersALinkSymbol(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int AS $$lib$$, $$sym$$ LANGUAGE c');
        self::assertSame('sym', (new OptionRule($lowering))->definition($tree->find('func_as')[0])->symbol?->value);
    }

    public function testTransformsLowersEachType(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int TRANSFORM FOR TYPE int, FOR TYPE text AS $$x$$');
        self::assertCount(2, (new OptionRule($lowering))->transforms($tree->find('transform_type_list')[0]));
    }

    public function testAlterLowersTheKind(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER PROCEDURE p() SECURITY INVOKER RESTRICT');
        self::assertSame(ObjectKind::Procedure, (new OptionRule($lowering))->alter($tree->find('AlterFunctionStmt')[0])->kind);
    }
}
