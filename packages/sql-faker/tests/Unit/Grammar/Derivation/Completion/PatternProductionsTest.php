<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation\Completion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\Completion\PatternProductions;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(PatternProductions::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class PatternProductionsTest extends TestCase
{
    public function testMatchingSeparatesPatternsAndRuleScopesWhenReusingItsCache(): void
    {
        $empty = new Production([]);
        $output = new Production([new Terminal('T')]);
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [$empty, $output]), 'other' => new ProductionRule('other', [$output])]);
        $choices = new PatternProductions($grammar);
        self::assertSame([$output], $choices->matching('root', ProductionPattern::at(1)));
        self::assertSame([$output], $choices->matching('root', ProductionPattern::at(1)));
        self::assertSame([$empty], $choices->matching('root', ProductionPattern::exactly()));
        self::assertSame([$empty, $output], $choices->matching('root', null));
        self::assertSame([], $choices->matching('other', ProductionPattern::at(1)));
        self::assertSame([], $choices->matching('missing', null));
    }
}
