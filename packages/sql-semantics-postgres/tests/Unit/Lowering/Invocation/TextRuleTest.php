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
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\TextRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(TextRule::class)]
#[Small]
final class TextRuleTest extends TestCase
{
    public function testExpressionLowersTheKeywordCalls(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT OVERLAY(1, 2), SUBSTRING(1)');
        $node = array_map(static fn ($target) => (new SyntaxRule($lowering))->subexpression($target->find('func_expr_common_subexpr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('OVERLAY(1, 2), SUBSTRING(1)', (new Lexical())->join($out->pieces()));
    }

    public function testExtractLowersTheThreeFieldSpellings(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT EXTRACT("YEAR" FROM 1), EXTRACT(month FROM 1), EXTRACT(\'dow\' FROM 1)');
        $node = array_map(static fn ($list) => (new TextRule($lowering))->extract($list), $tree->find('target_list')[0]->find('extract_list'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('EXTRACT("YEAR" FROM 1), EXTRACT(MONTH FROM 1), EXTRACT(\'dow\' FROM 1)', (new Lexical())->join($out->pieces()));
    }

    public function testOverlayLowersTheCount(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT OVERLAY(\'a\' PLACING \'b\' FROM 1 FOR 2)');
        $node = (new TextRule($lowering))->overlay($tree->find('overlay_list')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('OVERLAY(\'a\' PLACING \'b\' FROM 1 FOR 2)', (new Lexical())->join($out->pieces()));
    }

    public function testPositionLowersTheOperands(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT POSITION(\'b\' IN \'abc\')');
        $node = (new TextRule($lowering))->position($tree->find('position_list')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('POSITION(\'b\' IN \'abc\')', (new Lexical())->join($out->pieces()));
    }

    public function testSubstringLowersEveryForm(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT SUBSTRING(\'a\' FROM 1 FOR 2), SUBSTRING(\'a\' FOR 2 FROM 1), SUBSTRING(\'a\' FROM 1), SUBSTRING(\'a\' FOR 2), SUBSTRING(\'a\' SIMILAR \'b\' ESCAPE \'c\')');
        $node = array_map(static fn ($list) => (new TextRule($lowering))->substring($list), $tree->find('target_list')[0]->find('substr_list'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('SUBSTRING(\'a\' FROM 1 FOR 2), SUBSTRING(\'a\' FOR 2 FROM 1), SUBSTRING(\'a\' FROM 1), SUBSTRING(\'a\' FOR 2), SUBSTRING(\'a\' SIMILAR \'b\' ESCAPE \'c\')', (new Lexical())->join($out->pieces()));
    }

    public function testTrimLowersTheListForms(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT TRIM(BOTH \'x\' FROM \'a\'), TRIM(LEADING FROM \'a\'), TRIM(TRAILING \'a\', \'b\')');
        $node = array_map(static fn ($target) => (new SyntaxRule($lowering))->subexpression($target->find('func_expr_common_subexpr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('TRIM(\'x\' FROM \'a\'), TRIM(LEADING \'a\'), TRIM(TRAILING \'a\', \'b\')', (new Lexical())->join($out->pieces()));
    }
}
