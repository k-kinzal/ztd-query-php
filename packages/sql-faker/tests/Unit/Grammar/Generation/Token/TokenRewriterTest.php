<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\TerminalMappingRule;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;

#[CoversClass(TokenRewriter::class)]
#[UsesClass(TerminalMappingRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class TokenRewriterTest extends TestCase
{
    public function testRewriteAppliesEachRuleOnceInOrderAndPreservesTheOriginal(): void
    {
        $input = new TerminalSequence([new TerminalOccurrence('A', 1, [0], ['scope'])]);
        $pipeline = new TokenRewriter(
            new TerminalMappingRule('scope', 'A', 'B', 'first'),
            new TerminalMappingRule('scope', 'B', 'C', 'second'),
            new TerminalMappingRule('scope', 'C', 'A', 'third'),
        );
        $result = $pipeline->rewrite($input);
        self::assertSame(['A'], $result->names());
        self::assertSame(['first', 'second', 'third'], $result->rewrites);
        self::assertSame($input->original, $result->original);
        self::assertSame($input, (new TokenRewriter())->rewrite($input));
    }
}
