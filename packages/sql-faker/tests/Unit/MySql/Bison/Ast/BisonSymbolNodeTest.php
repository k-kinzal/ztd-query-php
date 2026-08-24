<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;

#[CoversClass(BisonSymbolNode::class)]
final class BisonSymbolNodeTest extends TestCase
{
    public function testType(): void
    {
        $node = new BisonSymbolNode(BisonSymbolType::Identifier, 'SELECT');

        self::assertSame(BisonSymbolType::Identifier, $node->type);
    }

    public function testValue(): void
    {
        $node = new BisonSymbolNode(BisonSymbolType::CharLiteral, ',');

        self::assertSame(',', $node->value);
    }
}
