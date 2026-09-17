<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Location;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrammarFile::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(Location::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Rule::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(TokenDeclaration::class)]
#[Small]
final class GrammarFileTest extends TestCase
{
    public function testRules(): void
    {
        $first = new Rule(new Symbol('a', new Location(2, 1)), null, [], null, null, false, new Location(2, 1));
        $second = new Rule(new Symbol('b', new Location(4, 1)), null, [], null, null, false, new Location(4, 1));
        $file = new GrammarFile([new TokenDeclaration([], new Location(1, 1)), $first, new PrecedenceDeclaration(Associativity::Left, [], new Location(3, 1)), $second]);

        self::assertSame([$first, $second], $file->rules());
    }

    public function testDeclarations(): void
    {
        $token = new TokenDeclaration([], new Location(1, 1));
        $left = new PrecedenceDeclaration(Associativity::Left, [], new Location(3, 1));
        $rule = new Rule(new Symbol('a', new Location(2, 1)), null, [], null, null, false, new Location(2, 1));
        $file = new GrammarFile([$token, $rule, $left]);

        self::assertSame([$token, $left], $file->declarations());
        self::assertCount(3, $file->items);
    }
}
