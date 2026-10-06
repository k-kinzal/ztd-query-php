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
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\CallRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(CallRule::class)]
#[Small]
final class CallRuleTest extends TestCase
{
    public function testCallLowersEveryCallForm(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT count(*), f(ALL 1), f(DISTINCT 1), f(VARIADIC 1), f(1, VARIADIC 2), f()');
        $node = array_map(static fn ($target) => (new CallRule($lowering))->call($target->find('func_expr')[0]), $tree->find('target_list')[0]->find('target_el'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->list($node);
        self::assertSame('count(*), f(1), f(DISTINCT 1), f(VARIADIC 1), f(1, VARIADIC 2), f()', (new Lexical())->join($out->pieces()));
    }

    public function testCallLowersAWindowlessCall(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (lower(a))');
        $node = (new CallRule($lowering))->call($tree->find('func_expr_windowless')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('lower(a)', (new Lexical())->join($out->pieces()));
    }

    public function testApplicationLowersTheNameAndArguments(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT s.f(a => 1)');
        $node = (new CallRule($lowering))->application($tree->find('func_application')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('s.f(a => 1)', (new Lexical())->join($out->pieces()));
    }

    public function testApplicationRejectsDistinctWithinGroup(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f(DISTINCT 1) WITHIN GROUP (ORDER BY 1)');
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('cannot use DISTINCT with WITHIN GROUP');
        (new CallRule($lowering))->call($tree->find('func_expr')[0]);
    }

    public function testWindowedLowersWithinGroup(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT mode() WITHIN GROUP (ORDER BY 1)');
        $node = (new CallRule($lowering))->call($tree->find('func_expr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('mode() WITHIN GROUP (ORDER BY 1)', (new Lexical())->join($out->pieces()));
    }

    public function testWithinGroupLowersNoClauseToNoOrdering(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f()');
        $node = (new CallRule($lowering))->withinGroup($tree->find('within_group_clause')[0]);
        self::assertSame([], $node);
    }

    public function testFilterLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f()');
        $node = (new CallRule($lowering))->filter($tree->find('filter_clause')[0]);
        self::assertNull($node);
    }

    public function testArgumentsLowersAnEmptyOptionalList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT SUBSTRING()');
        $node = (new CallRule($lowering))->arguments($tree->find('func_arg_list_opt')[0]);
        self::assertSame([], $node);
    }

    public function testArgumentLowersTheAssignmentSpelling(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f(a := 1)');
        $node = (new CallRule($lowering))->argument($tree->find('func_arg_expr')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('a := 1', (new Lexical())->join($out->pieces()));
    }

    public function testOrderLowersNoOrdering(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f(1)');
        $node = (new CallRule($lowering))->order($tree->find('opt_sort_clause')[0]);
        self::assertSame([], $node);
    }
}
