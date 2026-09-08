<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalMappingRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(RewriteRule::class)]
#[UsesClass(TerminalMappingRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class RewriteRuleTest extends TestCase
{
    public function testRewriteContractRetainsNonMatchingInput(): void
    {
        $rule = new TerminalMappingRule('option', 'IDENT', 'MODE', 'source');
        $input = TerminalSequence::fromNames(['IDENT']);
        self::assertSame($input, $rule->rewrite($input));
    }
}
