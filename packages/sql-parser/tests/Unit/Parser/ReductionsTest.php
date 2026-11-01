<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\Reductions;
use SqlParser\Table\TableRule;

#[CoversClass(Reductions::class)]
#[UsesClass(Token::class)]
#[UsesClass(Node::class)]
#[UsesClass(TableRule::class)]
#[Small]
final class ReductionsTest extends TestCase
{
    public function testNodeRetainsTheProductionAndNotifiesItsActualLookahead(): void
    {
        $token = new Token(1, 'NUM', '1', 0);
        $next = new Token(0, '$end', '', 1);
        $seen = [];
        $reductions = new Reductions(static function (Node $node, Token $lookahead) use (&$seen): void {
            $seen[] = [$node, $lookahead];
        });
        $node = $reductions->node('expr', new TableRule(2, 1, 4), [$token], $next);

        self::assertNotNull($node);
        self::assertSame(['expr', 4, [$token]], [$node->name, $node->ordinal, $node->children]);
        self::assertSame([[$node, $next]], $seen);
    }

    public function testNodeDoesNotNotifyHiddenGrammarActions(): void
    {
        $seen = [];
        $reductions = new Reductions(static function (Node $node) use (&$seen): void {
            $seen[] = $node;
        });

        self::assertNull($reductions->node('$@1', new TableRule(1, 0, 0, true), [], new Token(0, '$end', '', 0)));
        self::assertSame([], $seen);
    }
}
