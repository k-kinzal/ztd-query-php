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
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\JsonQueryRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\SyntaxRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(JsonQueryRule::class)]
#[Small]
final class JsonQueryRuleTest extends TestCase
{
    public function testFunctionLowersTheThreeQueryFunctions(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_QUERY(\'{}\', \'$\' RETURNING text WITH CONDITIONAL WRAPPER KEEP QUOTES EMPTY ON EMPTY), JSON_EXISTS(\'{}\', \'$\' UNKNOWN ON ERROR), JSON_VALUE(\'{}\', \'$\' NULL ON ERROR)');
        $node = array_map(static fn ($target) => (new SyntaxRule($lowering))->subexpression($target->find('func_expr_common_subexpr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('JSON_QUERY(\'{}\', \'$\' RETURNING text WITH CONDITIONAL WRAPPER KEEP QUOTES EMPTY ON EMPTY), JSON_EXISTS(\'{}\', \'$\' UNKNOWN ON ERROR), JSON_VALUE(\'{}\', \'$\' NULL ON ERROR)', (new Lexical())->join($out->pieces()));
    }

    public function testPassingLowersNoClauseToNoArgument(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_EXISTS(\'{}\', \'$\')');
        $node = (new JsonQueryRule($lowering))->passing($tree->find('json_passing_clause_opt')[0]);
        self::assertSame([], $node);
    }

    public function testBehaviorsLowersOnEmptyAlone(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_VALUE(\'{}\', \'$\' ERROR ON EMPTY)');
        $node = (new JsonQueryRule($lowering))->behaviors($tree->find('json_behavior_clause_opt')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('ERROR ON EMPTY', (new Lexical())->join($out->pieces()));
    }

    public function testBehaviorLowersEveryFixedBehavior(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_QUERY(\'{}\', \'$\' EMPTY ARRAY ON EMPTY EMPTY OBJECT ON ERROR)');
        $node = (new JsonQueryRule($lowering))->behavior($tree->find('json_behavior')[1]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('EMPTY OBJECT', (new Lexical())->join($out->pieces()));
    }

    public function testWrapperLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_QUERY(\'{}\', \'$\')');
        $node = (new JsonQueryRule($lowering))->wrapper($tree->find('json_wrapper_behavior')[0]);
        self::assertNull($node);
    }

    public function testQuotesLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT JSON_QUERY(\'{}\', \'$\')');
        $node = (new JsonQueryRule($lowering))->quotes($tree->find('json_quotes_clause_opt')[0]);
        self::assertNull($node);
    }
}
