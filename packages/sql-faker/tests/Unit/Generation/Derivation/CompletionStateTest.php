<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\CompletionState;
use SqlFaker\Grammar\Model\NonTerminal;

#[CoversClass(CompletionState::class)]
#[UsesClass(NonTerminal::class)]
final class CompletionStateTest extends TestCase
{
    public function testKeySharesEquivalentContinuationsAcrossDifferentSpentBudgets(): void
    {
        $state = new CompletionState([new NonTerminal('a')], ['a' => 1], true, 1);
        self::assertSame($state->key(), (new CompletionState([new NonTerminal('a')], ['a' => 1], true, 5))->key());
        self::assertNotSame($state->key(), (new CompletionState([new NonTerminal('b')], ['a' => 1], true, 1))->key());
        self::assertNotSame($state->key(), (new CompletionState([new NonTerminal('a')], ['a' => 2], true, 1))->key());
        self::assertNotSame($state->key(), (new CompletionState([new NonTerminal('a')], ['a' => 1], false, 1))->key());
    }
}
