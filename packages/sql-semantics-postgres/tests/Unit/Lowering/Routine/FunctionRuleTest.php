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
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\FunctionRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\DefaultSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultTable;

#[CoversClass(FunctionRule::class)]
#[Small]
final class FunctionRuleTest extends TestCase
{
    public function testCreateLowersEachForm(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS TABLE (a int) AS $$x$$ LANGUAGE sql; CREATE OR REPLACE PROCEDURE p() BEGIN ATOMIC END');
        $rule = new FunctionRule($lowering);
        $function = $rule->create($tree->find('CreateFunctionStmt')[0]);
        $procedure = $rule->create($tree->find('CreateFunctionStmt')[1]);
        self::assertInstanceOf(ResultTable::class, $function->returns);
        self::assertSame([false, true, true, 2], [$function->procedure, $procedure->procedure, $procedure->replace, count($function->options)]);
    }

    public function testParametersLowersAnEmptyList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int RETURN 1');
        self::assertSame([], (new FunctionRule($lowering))->parameters($tree->find('func_args_with_defaults')[0])->parameters);
    }

    public function testParameterLowersBothDefaultSpellings(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f(a int DEFAULT 1, b int = 2) RETURNS int RETURN 1');
        $rule = new FunctionRule($lowering);
        self::assertSame([DefaultSpelling::Keyword, DefaultSpelling::EqualsSign], [$rule->parameter($tree->find('func_arg_with_default')[0])->defaultSpelling, $rule->parameter($tree->find('func_arg_with_default')[1])->defaultSpelling]);
    }

    public function testResultLowersSetof(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS SETOF int RETURN 1');
        self::assertTrue((new FunctionRule($lowering))->result($tree->find('func_return')[0])->setOf);
    }

    public function testTableLowersTheColumns(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS TABLE (a int, b text) RETURN 1');
        self::assertCount(2, (new FunctionRule($lowering))->table($tree->find('table_func_column_list')[0])->columns);
    }

    public function testBodyLowersNoBody(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int AS $$x$$');
        self::assertNull((new FunctionRule($lowering))->body($tree->find('opt_routine_body')[0]));
    }

    public function testReturnLowersTheExpression(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int RETURN 1');
        $value = (new FunctionRule($lowering))->return($tree->find('ReturnStmt')[0])->value;
        self::assertInstanceOf(Constant::class, $value);
        self::assertEquals(new IntegerConstant('1'), $value->value);
    }

    public function testStatementsDropsEmptyStatements(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE FUNCTION f() RETURNS int BEGIN ATOMIC ; SELECT 1; ; RETURN 1; END');
        self::assertCount(2, (new FunctionRule($lowering))->statements($tree->find('routine_body_stmt_list')[0]));
    }
}
