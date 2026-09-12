<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\TerminalMappingRule;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(TerminalMappingRule::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
final class TerminalMappingRuleTest extends TestCase
{
    public function testRewriteChangesOnlyDirectChildrenOfTheNamedRule(): void
    {
        $direct = new TerminalOccurrence('IDENT', 1, [0], ['option']);
        $nested = new TerminalOccurrence('IDENT', 3, [0, 2], ['option', 'expression']);
        $outside = new TerminalOccurrence('IDENT', 4);
        $original = [$direct, $nested, $outside];
        $input = new TerminalSequence($original, $original);
        $result = (new TerminalMappingRule('option', 'IDENT', 'MODE', 'parser.c:option'))->rewrite($input);
        self::assertSame(['MODE', 'IDENT', 'IDENT'], $result->names());
        self::assertSame($original, $result->original);
        self::assertSame($nested, $result->terminals[1]);
        self::assertSame($direct->id, $result->terminals[0]->id);
        self::assertSame('parser.c:option', $result->terminals[0]->rewrite);
    }
}
