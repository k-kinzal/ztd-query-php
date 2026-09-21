<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ClosureIndex;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(ClosureIndex::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class ClosureIndexTest extends TestCase
{
    public function testReachable(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('NUM');
        $builder->terminal('(');
        $builder->terminal(')');
        $builder->rule('expr', ['term']);
        $builder->rule('term', ['factor']);
        $builder->rule('factor', ['NUM']);
        $builder->rule('factor', ['(', 'expr', ')']);
        $grammar = $builder->build();
        $index = new ClosureIndex($grammar);
        $symbols = $grammar->symbols;
        $reached = $index->reachable($grammar, $symbols->id('expr') ?? -1);
        sort($reached);

        self::assertSame([$symbols->id('expr'), $symbols->id('term'), $symbols->id('factor')], $reached);
        self::assertSame([$symbols->id('factor')], $index->reachable($grammar, $symbols->id('factor') ?? -1));
    }

    public function testStartsWith(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('NUM');
        $builder->terminal('(');
        $builder->terminal(')');
        $builder->rule('expr', ['term']);
        $builder->rule('term', ['NUM']);
        $builder->rule('term', ['(', 'expr', ')']);
        $grammar = $builder->build();
        $index = new ClosureIndex($grammar);
        $symbols = $grammar->symbols;
        $width = $index->itemWidth;

        self::assertSame(4, $width);
        self::assertSame([$symbols->id('term') => [1 * $width + 1], $symbols->id('NUM') => [2 * $width + 1], $symbols->id('(') => [3 * $width + 1]], $index->startsWith($symbols->id('expr') ?? -1));
        self::assertSame([], $index->startsWith(99));
    }

    public function testEmptyRules(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['opt', 'A']);
        $builder->rule('opt', []);
        $builder->rule('opt', ['A']);
        $grammar = $builder->build();
        $index = new ClosureIndex($grammar);

        self::assertSame([2], $index->emptyRules($grammar->symbols->id('s') ?? -1));
        self::assertSame([2], $index->emptyRules($grammar->symbols->id('opt') ?? -1));
        self::assertSame([], $index->emptyRules(99));
    }
}
