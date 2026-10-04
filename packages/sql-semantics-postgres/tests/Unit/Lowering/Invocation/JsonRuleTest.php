<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\JsonRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\SyntaxRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\KeyValueSpelling;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(JsonRule::class)]
#[Small]
final class JsonRuleTest extends TestCase
{
    public function testCreationLowersEveryConstructor(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECT(\'a\' : 1 NULL ON NULL WITH UNIQUE RETURNING json), JSON_OBJECT(), JSON_OBJECT(\'{a,b}\'), JSON_ARRAY(1 ABSENT ON NULL), JSON_ARRAY(), JSON(1), JSON_SCALAR(1), JSON_SERIALIZE(1)');
        $node = array_map(static fn ($target) => (new SyntaxRule($lowering))->subexpression($target->find('func_expr_common_subexpr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('JSON_OBJECT(\'a\' : 1 NULL ON NULL WITH UNIQUE KEYS RETURNING JSON), JSON_OBJECT(), JSON_OBJECT(\'{a,b}\'), JSON_ARRAY(1 ABSENT ON NULL), JSON_ARRAY(), JSON(1), JSON_SCALAR(1), JSON_SERIALIZE(1)', (new Lexical())->join($out->pieces()));
    }

    public function testCreationLowersThePostgreSql16Forms(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql166)), new Leaves(), GrammarRelease::PostgreSql166);
        $tree = (new PostgreSqlParser('pg-16.6'))->parse('SELECT JSON_OBJECT(\'a\' : 1 RETURNING text FORMAT JSON ENCODING utf8)');
        $node = (new SyntaxRule($lowering))->subexpression($tree->find('func_expr_common_subexpr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('JSON_OBJECT(\'a\' : 1 RETURNING text FORMAT JSON ENCODING utf8)', (new Lexical())->join($out->pieces()));
    }

    public function testAggregateLowersTheJsonAggregates(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECTAGG(\'a\' VALUE 1 ABSENT ON NULL)');
        $node = (new JsonRule($lowering))->aggregate($tree->find('json_aggregate_func')[0], null, null);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('JSON_OBJECTAGG(\'a\' VALUE 1 ABSENT ON NULL)', (new Lexical())->join($out->pieces()));
    }

    public function testOrderLowersNoOrdering(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_ARRAYAGG(1)');
        $node = (new JsonRule($lowering))->order($tree->find('json_array_aggregate_order_by_clause_opt')[0]);
        self::assertSame([], $node);
    }

    public function testValueLowersTheFormat(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON(\'{}\' FORMAT JSON ENCODING UTF8)');
        $node = (new JsonRule($lowering))->value($tree->find('json_value_expr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('\'{}\' FORMAT JSON ENCODING utf8', (new Lexical())->join($out->pieces()));
    }

    public function testValuesLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_ARRAY(1, 2, 3)');
        $node = (new JsonRule($lowering))->values($tree->find('json_value_expr_list')[0]);
        self::assertCount(3, $node);
    }

    public function testPairsLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECT(\'a\' : 1, \'b\' : 2)');
        $node = (new JsonRule($lowering))->pairs($tree->find('json_name_and_value_list')[0]);
        self::assertCount(2, $node);
    }

    public function testKeyValueLowersTheColonSpelling(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECT(\'a\' : 1)');
        $node = (new JsonRule($lowering))->keyValue($tree->find('json_name_and_value')[0]);
        self::assertSame(KeyValueSpelling::Colon, $node->spelling);
    }

    public function testFormatLowersTheEncoding(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON(\'{}\' FORMAT JSON ENCODING utf16)');
        $node = (new JsonRule($lowering))->format($tree->find('json_format_clause_opt')[0]);
        self::assertSame(JsonEncoding::Utf16, $node?->encoding());
    }

    public function testEncodingClauseLowersThePostgreSql16Encoding(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql166)), new Leaves(), GrammarRelease::PostgreSql166);
        $tree = (new PostgreSqlParser('pg-16.6'))->parse('SELECT JSON_ARRAY(1 FORMAT JSON ENCODING utf32)');
        $node = (new JsonRule($lowering))->encodingClause($tree->find('json_encoding_clause_opt')[0]);
        self::assertSame('utf32', $node?->value);
    }

    public function testEncodingRejectsAnUnknownEncoding(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON(\'{}\' FORMAT JSON ENCODING latin1)');
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('unrecognized JSON encoding: latin1');
        (new JsonRule($lowering))->format($tree->find('json_format_clause_opt')[0]);
    }

    public function testReturningLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_SERIALIZE(1)');
        $node = (new JsonRule($lowering))->returning($tree->find('json_returning_clause_opt')[0]);
        self::assertNull($node);
    }

    public function testNullsLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_ARRAY(1)');
        $node = (new JsonRule($lowering))->nulls($tree->find('json_array_constructor_null_clause_opt')[0]);
        self::assertNull($node);
    }

    public function testUniquenessLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_OBJECT(\'a\' : 1)');
        $node = (new JsonRule($lowering))->uniqueness($tree->find('json_key_uniqueness_constraint_opt')[0]);
        self::assertNull($node);
    }
}
