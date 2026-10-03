<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\Invocations;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Invocations::class)]
#[Small]
final class InvocationsTest extends TestCase
{
    public function testCallIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT count(*) OVER (PARTITION BY a) FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: func_expr: func_application within_group_clause filter_clause over_clause');
        $lowering->invocations->call($tree->find('func_expr')[0]);
    }

    public function testArgumentsIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f(1, b => 2)');
        $this->expectExceptionMessage('No semantic rule is implemented for: func_arg_list: func_arg_list , func_arg_expr');
        $lowering->invocations->arguments($tree->find('func_arg_list')[0]);
    }

    public function testWindowIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT count(*) OVER (PARTITION BY a) FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: window_specification: ( opt_existing_window_name opt_partition_clause opt_sort_clause opt_frame_clause )');
        $lowering->invocations->window($tree->find('window_specification')[0]);
    }

    public function testXmlPassingIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT XMLEXISTS('/a' PASSING x) FROM t");
        $this->expectExceptionMessage('No semantic rule is implemented for: xmlexists_argument: PASSING c_expr');
        $lowering->invocations->xmlPassing($tree->find('xmlexists_argument')[0]);
    }

    public function testJsonValueIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_OBJECT('a': 1 WITH UNIQUE KEYS)");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_value_expr: a_expr json_format_clause_opt');
        $lowering->invocations->jsonValue($tree->find('json_value_expr')[0]);
    }

    public function testJsonPassingIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_QUERY(x, 'p' PASSING 1 AS a WITH WRAPPER KEEP QUOTES ERROR ON ERROR) FROM t");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_passing_clause_opt: PASSING json_arguments');
        $lowering->invocations->jsonPassing($tree->find('json_passing_clause_opt')[0]);
    }

    public function testJsonFormatIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_OBJECT('a': 1 WITH UNIQUE KEYS)");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_format_clause_opt:');
        $lowering->invocations->jsonFormat($tree->find('json_format_clause_opt')[0]);
    }

    public function testJsonBehaviorIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_EXISTS(x, 'p') FROM t");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_on_error_clause_opt:');
        $lowering->invocations->jsonBehavior($tree->find('json_on_error_clause_opt')[0]);
    }

    public function testJsonWrapperIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_QUERY(x, 'p' PASSING 1 AS a WITH WRAPPER KEEP QUOTES ERROR ON ERROR) FROM t");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_wrapper_behavior: WITH WRAPPER');
        $lowering->invocations->jsonWrapper($tree->find('json_wrapper_behavior')[0]);
    }

    public function testJsonQuotesIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_QUERY(x, 'p' PASSING 1 AS a WITH WRAPPER KEEP QUOTES ERROR ON ERROR) FROM t");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_quotes_clause_opt: KEEP QUOTES');
        $lowering->invocations->jsonQuotes($tree->find('json_quotes_clause_opt')[0]);
    }

    public function testJsonKeyUniquenessIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT JSON_OBJECT('a': 1 WITH UNIQUE KEYS)");
        $this->expectExceptionMessage('No semantic rule is implemented for: json_key_uniqueness_constraint_opt: WITH UNIQUE KEYS');
        $lowering->invocations->jsonKeyUniqueness($tree->find('json_key_uniqueness_constraint_opt')[0]);
    }
}
