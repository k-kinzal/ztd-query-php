<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(Lists::class)]
#[Medium]
final class ListsTest extends TestCase
{
    public function testElementsFlattenALeftRecursiveSpineInSourceOrder(): void
    {
        $first = new Node('item', 0, []);
        $comma = new Token(1, 'COMMA', ',', 1);
        $second = new Node('item', 0, []);
        $third = new Node('item', 0, []);
        $list = new Node('list', 1, [new Node('list', 1, [new Node('list', 0, [$first]), $comma, $second]), new Token(1, 'COMMA', ',', 3), $third]);

        $elements = (new Lists())->elements($list);

        self::assertCount(5, $elements);
        self::assertSame([$first, $comma, $second], array_slice($elements, 0, 3));
        self::assertSame($third, $elements[4]);
    }

    public function testElementsFlattenARightRecursiveSpine(): void
    {
        $first = new Node('item', 0, []);
        $second = new Node('item', 0, []);
        $list = new Node('list', 1, [$first, new Node('list', 0, [$second])]);

        self::assertSame([$first, $second], (new Lists())->elements($list));
    }

    public function testItemsDropTheSeparatorTokens(): void
    {
        $first = new Node('item', 0, []);
        $second = new Node('item', 0, []);
        $list = new Node('list', 1, [new Node('list', 0, [$first]), new Token(1, 'COMMA', ',', 1), $second]);

        self::assertSame([$first, $second], (new Lists())->items($list));
    }

    public function testItemsOfAParsedStatementList(): void
    {
        $platform = Platforms::of('sqlite');
        $profile = (new Semantics(Dialect::Sqlite))->profile();
        $tree = $platform->parser($profile)->parse('SELECT 1; SELECT 2; SELECT 3');
        $list = $tree->children[0];

        self::assertInstanceOf(Node::class, $list);
        self::assertSame('cmdlist', $list->name);
        self::assertSame(['ecmd', 'ecmd', 'ecmd'], array_map(static fn (Node $item): string => $item->name, (new Lists())->items($list)));
    }
}
