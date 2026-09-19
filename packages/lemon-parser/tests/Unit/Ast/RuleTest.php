<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rule::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(Location::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class RuleTest extends TestCase
{
    public function testWithPrecedence(): void
    {
        $rule = new Rule(new Symbol('expr', new Location(1, 1)), 'A', [], null, null, false, new Location(1, 1));

        $marked = $rule->withPrecedence(new Symbol('PLUS', new Location(1, 20)));

        self::assertSame('PLUS', $marked->precedence?->name);
        self::assertNull($rule->precedence);
        self::assertSame(['expr', 'A', '1:1'], [$marked->lhs->name, $marked->lhsAlias, (string) $marked->location]);
    }

    public function testWithCode(): void
    {
        $rule = new Rule(new Symbol('expr', new Location(1, 1)), null, [], null, null, false, new Location(1, 1));

        $withCode = $rule->withCode(new CodeBlock(' x ', new Location(1, 20)));

        self::assertSame(' x ', $withCode->code?->code);
        self::assertNull($rule->code);
    }

    public function testWithNeverReduce(): void
    {
        $rule = new Rule(new Symbol('expr', new Location(1, 1)), null, [], null, null, false, new Location(1, 1));

        self::assertTrue($rule->withNeverReduce()->neverReduce);
        self::assertFalse($rule->neverReduce);
    }

    public function testSymbols(): void
    {
        $items = [
            new RhsItem([new Symbol('expr', new Location(1, 10))], 'B'),
            new RhsItem([new Symbol('PLUS', new Location(1, 15)), new Symbol('MINUS', new Location(1, 20))], null),
            new RhsItem([new Symbol('NUM', new Location(1, 26))], 'C'),
        ];
        $rule = new Rule(new Symbol('expr', new Location(1, 1)), null, $items, null, null, false, new Location(1, 1));

        self::assertSame(['expr', 'PLUS', 'MINUS', 'NUM'], array_map(static fn (Symbol $symbol): string => $symbol->name, $rule->symbols()));
    }
}
