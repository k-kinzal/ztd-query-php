<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rule::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Location::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class RuleTest extends TestCase
{
    public function testAlternatives(): void
    {
        $rule = new Rule(new Symbol(SymbolKind::Identifier, 'expr', new Location(4, 1)), 'e', [new Alternative([], new Location(4, 9))], new Location(4, 1));

        self::assertSame('expr', $rule->name->value);
        self::assertSame('e', $rule->namedReference);
        self::assertCount(1, $rule->alternatives);
        self::assertSame('4:1', (string) $rule->location);
    }
}
