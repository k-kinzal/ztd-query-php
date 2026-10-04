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
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Invocations::class)]
#[Small]
final class InvocationsTest extends TestCase
{
    public function testCallLowersAFunctionCall(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f(1, x => 2) FILTER (WHERE TRUE) OVER w');
        $node = $lowering->invocations->call($tree->find('func_expr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('f(1, x => 2) FILTER (WHERE TRUE) OVER w', (new Lexical())->join($out->pieces()));
    }

    public function testArgumentsLowersAnArgumentList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f(1, x := 2)');
        $node = $lowering->invocations->arguments($tree->find('func_arg_list')[0]);
        self::assertCount(2, $node);
        self::assertInstanceOf(NamedArgument::class, $node[1]);
    }

    public function testSubexpressionLowersASqlSyntaxFunction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CURRENT_TIME(2)');
        $node = $lowering->invocations->subexpression($tree->find('func_expr_common_subexpr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('CURRENT_TIME(2)', (new Lexical())->join($out->pieces()));
    }

    public function testWindowLowersAWindowSpecification(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() OVER (w PARTITION BY 1 ROWS 1 PRECEDING EXCLUDE TIES)');
        $node = $lowering->invocations->window($tree->find('window_specification')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('(w PARTITION BY 1 ROWS 1 PRECEDING EXCLUDE TIES)', (new Lexical())->join($out->pieces()));
    }

    public function testOverLowersAWindowName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() OVER w');
        $node = $lowering->invocations->over($tree->find('over_clause')[0]);
        self::assertEquals(new Name('w'), $node);
    }

    public function testFilterLowersTheCondition(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() FILTER (WHERE TRUE)');
        $node = $lowering->invocations->filter($tree->find('filter_clause')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('TRUE', (new Lexical())->join($out->pieces()));
    }

    public function testXmlPassingLowersThePassingClause(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLEXISTS(\'//a\' PASSING BY VALUE \'<a/>\' BY REF)');
        $node = $lowering->invocations->xmlPassing($tree->find('xmlexists_argument')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('PASSING \'<a/>\'', (new Lexical())->join($out->pieces()));
    }

    public function testXmlAttributesLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLFOREST(1 AS a, 2)');
        $node = $lowering->invocations->xmlAttributes($tree->find('xml_attribute_list')[0]);
        self::assertCount(2, $node);
        self::assertNull($node[1]->name);
    }

    public function testJsonValueLowersAFormattedValue(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_SCALAR(1), JSON(\'{}\' FORMAT JSON)');
        $node = $lowering->invocations->jsonValue($tree->find('json_value_expr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('\'{}\' FORMAT JSON', (new Lexical())->join($out->pieces()));
    }

    public function testJsonPassingLowersTheArguments(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_EXISTS(\'{}\', \'$.a\' PASSING 1 AS x, 2 AS y)');
        $node = $lowering->invocations->jsonPassing($tree->find('json_passing_clause_opt')[0]);
        self::assertCount(2, $node);
        self::assertSame('y', $node[1]->name->value);
    }

    public function testJsonFormatLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON(\'{}\')');
        $node = $lowering->invocations->jsonFormat($tree->find('json_format_clause_opt')[0]);
        self::assertNull($node);
    }

    public function testJsonReturningLowersTheType(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_SERIALIZE(\'{}\' RETURNING bytea)');
        $node = $lowering->invocations->jsonReturning($tree->find('json_returning_clause_opt')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('RETURNING bytea', (new Lexical())->join($out->pieces()));
    }

    public function testJsonKeyValueLowersAPair(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECT(\'a\' VALUE 1)');
        $node = $lowering->invocations->jsonKeyValue($tree->find('json_name_and_value')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('\'a\' VALUE 1', (new Lexical())->join($out->pieces()));
    }

    public function testJsonNullsLowersTheNullClause(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_ARRAY(1 NULL ON NULL)');
        $node = $lowering->invocations->jsonNulls($tree->find('json_array_constructor_null_clause_opt')[0]);
        self::assertSame(JsonNullHandling::Keep, $node);
    }

    public function testJsonBehaviorLowersBothBehaviors(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_VALUE(\'{}\', \'$\' DEFAULT 1 ON EMPTY ERROR ON ERROR)');
        $node = $lowering->invocations->jsonBehavior($tree->find('json_behavior_clause_opt')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('DEFAULT 1 ON EMPTY ERROR ON ERROR', (new Lexical())->join($out->pieces()));
    }

    public function testJsonWrapperDropsTheNoiseWords(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_QUERY(\'{}\', \'$\' WITH UNCONDITIONAL ARRAY WRAPPER)');
        $node = $lowering->invocations->jsonWrapper($tree->find('json_wrapper_behavior')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('WITH WRAPPER', (new Lexical())->join($out->pieces()));
    }

    public function testJsonQuotesDropsTheNoiseWords(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_QUERY(\'{}\', \'$\' OMIT QUOTES ON SCALAR STRING)');
        $node = $lowering->invocations->jsonQuotes($tree->find('json_quotes_clause_opt')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('OMIT QUOTES', (new Lexical())->join($out->pieces()));
    }

    public function testJsonKeyUniquenessLowersTheClause(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECT(\'a\' : 1 WITHOUT UNIQUE)');
        $node = $lowering->invocations->jsonKeyUniqueness($tree->find('json_key_uniqueness_constraint_opt')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('WITHOUT UNIQUE KEYS', (new Lexical())->join($out->pieces()));
    }
}
