<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolForm;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolNode;

#[CoversClass(BisonSymbolNode::class)]
final class BisonSymbolNodeTest extends TestCase
{
    public function testType(): void
    {
        $node = new BisonSymbolNode(BisonSymbolForm::Identifier, 'SELECT');

        self::assertSame(BisonSymbolForm::Identifier, $node->type);
    }

    public function testValue(): void
    {
        $node = new BisonSymbolNode(BisonSymbolForm::CharLiteral, ',');

        self::assertSame(',', $node->value);
    }
}
