<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\OrderingNodes;

#[CoversClass(OrderingNodes::class)]
final class OrderingNodesTest extends TestCase
{
    public function testReadFlattensALeftRecursiveListFromItsInnermostItem(): void
    {
        $leaf = new Node('sortlist', 0, [new Token(0, 'ID', 'a', 0)]);
        $middle = new Node('sortlist', 0, [$leaf, new Token(0, 'COMMA', ',', 1), new Token(0, 'ID', 'b', 2)]);
        $outer = new Node('sortlist', 0, [$middle, new Token(0, 'COMMA', ',', 3), new Token(0, 'ID', 'c', 4)]);
        $read = OrderingNodes::read($outer);
        self::assertCount(3, $read);
        self::assertSame($leaf, $read[0]);
        self::assertSame($middle, $read[1]);
        self::assertSame($outer, $read[2]);
    }

    public function testReadReturnsASingleItemListUnchanged(): void
    {
        $single = new Node('sortlist', 0, [new Token(0, 'ID', 'a', 0)]);
        self::assertSame([$single], OrderingNodes::read($single));
    }

    public function testReadDoesNotDescendIntoDifferentlyNamedOperands(): void
    {
        $operand = new Node('sortlist', 0, [new Token(0, 'ID', 'a', 0)]);
        $list = new Node('orderby_opt', 0, [new Node('expr', 0, [$operand])]);
        self::assertSame([$list], OrderingNodes::read($list));
    }
}
