<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Php;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Nop;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Php\NodeText;

#[CoversClass(NodeText::class)]
final class NodeTextTest extends TestCase
{
    public function testRenderWritesAnExpressionOnOneLine(): void
    {
        self::assertSame('$name', (new NodeText())->render(new Variable('name')));
    }

    public function testRenderTruncatesALongExpression(): void
    {
        $rendered = (new NodeText())->render(new String_(str_repeat('a', 200)));
        self::assertSame(NodeText::MAX_LENGTH, mb_strlen($rendered));
        self::assertStringEndsWith('…', $rendered);
    }

    public function testRenderHandlesAStatement(): void
    {
        self::assertSame('', (new NodeText())->render(new Nop()));
    }

    public function testAsExprReplacesAnythingThatIsNotAnExpression(): void
    {
        $reader = new NodeText();
        $expression = new Variable('a');
        self::assertSame($expression, $reader->asExpr($expression));
        self::assertInstanceOf(String_::class, $reader->asExpr(new Nop()));
    }
}
