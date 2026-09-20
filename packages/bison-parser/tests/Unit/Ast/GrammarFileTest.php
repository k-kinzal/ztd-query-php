<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Epilogue;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrammarFile::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Epilogue::class)]
#[UsesClass(Flag::class)]
#[UsesClass(Location::class)]
#[UsesClass(Rule::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolKind::class)]
#[Small]
final class GrammarFileTest extends TestCase
{
    public function testRules(): void
    {
        $rule = new Rule(new Symbol(SymbolKind::Identifier, 'start', new Location(3, 1)), null, [new Alternative([], new Location(3, 8))], new Location(3, 1));
        $other = new Rule(new Symbol(SymbolKind::Identifier, 'other', new Location(4, 1)), null, [new Alternative([], new Location(4, 8))], new Location(4, 1));
        $file = new GrammarFile([new Flag('debug', '%debug', new Location(1, 1))], [new Flag('locations', '%locations', new Location(2, 1)), $rule, $other], null);

        self::assertSame([$rule, $other], $file->rules());
        self::assertNull($file->epilogue);
    }

    public function testAllDeclarations(): void
    {
        $before = new Flag('debug', '%debug', new Location(1, 1));
        $among = new Flag('locations', '%locations', new Location(3, 1));
        $rule = new Rule(new Symbol(SymbolKind::Identifier, 'start', new Location(2, 1)), null, [new Alternative([], new Location(2, 8))], new Location(2, 1));
        $file = new GrammarFile([$before], [$rule, $among], new Epilogue('', new Location(4, 3)));

        self::assertSame([$before, $among], $file->allDeclarations());
        self::assertSame('4:3', (string) $file->epilogue?->location);
    }
}
