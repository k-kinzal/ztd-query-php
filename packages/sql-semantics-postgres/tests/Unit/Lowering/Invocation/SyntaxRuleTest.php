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
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\SyntaxRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\CastSpelling;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(SyntaxRule::class)]
#[Small]
final class SyntaxRuleTest extends TestCase
{
    public function testSubexpressionLowersTheBareKeywordFunctions(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CURRENT_ROLE');
        $node = (new SyntaxRule($lowering))->subexpression($tree->find('func_expr_common_subexpr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('CURRENT_ROLE', (new Lexical())->join($out->pieces()));
    }

    public function testSpecialBuildsTheFunctionSpellingOfCast(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT CAST(1 AS int)');
        $node = (new SyntaxRule($lowering))->subexpression($tree->find('func_expr_common_subexpr')[0]);
        self::assertInstanceOf(Cast::class, $node);
        self::assertSame(CastSpelling::Function, $node->spelling);
    }

    public function testSubexpressionRoutesToTheFamilyRules(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT XMLCONCAT(NULL)');
        $node = (new SyntaxRule($lowering))->subexpression($tree->find('func_expr_common_subexpr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('XMLCONCAT(NULL)', (new Lexical())->join($out->pieces()));
    }

    public function testSpecialLowersTheRemainingFunctions(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT COLLATION FOR (1), TREAT(1 AS int), NORMALIZE(\'a\', NFC), NULLIF(1, 2), COALESCE(1), GREATEST(1), LEAST(1), MERGE_ACTION()');
        $node = array_map(static fn ($target) => (new SyntaxRule($lowering))->subexpression($target->find('func_expr_common_subexpr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('COLLATION FOR (1), TREAT(1 AS INT), NORMALIZE(\'a\', NFC), NULLIF(1, 2), COALESCE(1), GREATEST(1), LEAST(1), MERGE_ACTION()', (new Lexical())->join($out->pieces()));
    }
}
