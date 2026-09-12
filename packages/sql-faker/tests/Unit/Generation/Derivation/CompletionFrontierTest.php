<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\CompletionFrontier;
use SqlFaker\Generation\Derivation\CompletionState;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(CompletionFrontier::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
final class CompletionFrontierTest extends TestCase
{
    public function testTakeOrdersAffordableCompletionsByEstimatedCost(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])])]);
        $frontier = new CompletionFrontier(new CompletionCosts($grammar, static fn (string $name): bool => false), 5);
        $frontier->offer([new NonTerminal('leaf')], ['leaf' => 1], true, 3);
        $frontier->offer([new NonTerminal('leaf')], ['leaf' => 0], true, 1);
        self::assertSame(1, $frontier->take()?->spent);
        self::assertSame(3, $frontier->take()?->spent);
        self::assertNull($frontier->take());
    }

    public function testOfferPrunesOverBudgetAndEquivalentMoreExpensiveStates(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])])]);
        $frontier = new CompletionFrontier(new CompletionCosts($grammar, static fn (string $name): bool => false), 3);
        $frontier->offer([new NonTerminal('leaf')], [], true, 1);
        $frontier->offer([new NonTerminal('leaf')], [], true, 2);
        $frontier->offer([new NonTerminal('leaf')], ['leaf' => 1], true, 3);
        $frontier->offer([], [], false, 4);
        self::assertSame(1, $frontier->take()?->spent);
        self::assertNull($frontier->take());
    }

    public function testOfferRemembersAlreadyEmittedOutputBeforeErasingTerminals(): void
    {
        $grammar = new Grammar('leaf', ['leaf' => new ProductionRule('leaf', [new Production([])])]);
        $frontier = new CompletionFrontier(new CompletionCosts($grammar, static fn (string $name): bool => $name === 'END'), 2);
        $frontier->offer([new Terminal('END'), new NonTerminal('leaf')], [], true, 0);
        self::assertNull($frontier->take());
        $frontier->offer([new Terminal('T'), new NonTerminal('leaf')], [], true, 0);
        $state = $frontier->take();
        self::assertNotNull($state);
        self::assertFalse($state->nonEmpty);
        self::assertCount(1, $state->symbols);
    }
}
