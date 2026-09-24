<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\Server\Literals;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\LiteralKind;

#[CoversClass(Literals::class)]
#[Medium]
final class LiteralsTest extends TestCase
{
    public function testTextBindsTheOnlyTokenOfAnOperand(): void
    {
        $tree = (new DialectParser(Dialect::MySql))->parse("PURGE BINARY LOGS TO 'x'");
        $literal = Literals::text(Tree::outer($tree, ['TEXT_STRING_sys'])[0]);
        self::assertSame("'x'", $literal->text);
        self::assertSame(LiteralKind::Text, $literal->literalKind);
    }
}
