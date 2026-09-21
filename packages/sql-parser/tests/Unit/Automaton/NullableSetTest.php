<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\NullableSet;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(NullableSet::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class NullableSetTest extends TestCase
{
    public function testIsNullable(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['opt', 'A']);
        $builder->rule('opt', ['pair']);
        $builder->rule('opt', ['A']);
        $builder->rule('pair', ['empty', 'empty']);
        $builder->rule('empty', []);
        $grammar = $builder->build();
        $nullable = new NullableSet($grammar);

        self::assertTrue($nullable->isNullable($grammar->symbols->id('empty') ?? -1));
        self::assertTrue($nullable->isNullable($grammar->symbols->id('pair') ?? -1));
        self::assertTrue($nullable->isNullable($grammar->symbols->id('opt') ?? -1));
        self::assertFalse($nullable->isNullable($grammar->symbols->id('s') ?? -1));
        self::assertFalse($nullable->isNullable($grammar->symbols->id('A') ?? -1));
    }

    public function testTailNullable(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['A', 'empty', 'empty']);
        $builder->rule('empty', []);
        $grammar = $builder->build();
        $nullable = new NullableSet($grammar);
        $rhs = $grammar->rules[1]->rhs;

        self::assertFalse($nullable->tailNullable($rhs, 0));
        self::assertTrue($nullable->tailNullable($rhs, 1));
        self::assertTrue($nullable->tailNullable($rhs, 3));
    }
}
