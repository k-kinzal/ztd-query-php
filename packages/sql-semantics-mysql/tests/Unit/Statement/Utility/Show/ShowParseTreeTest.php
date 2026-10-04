<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowParseTree;

#[CoversClass(ShowParseTree::class)]
#[Medium]
final class ShowParseTreeTest extends TestCase
{
    public function testDeriveStatementInspectsTheStatement(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW PARSE_TREE SELECT a FROM t');
        self::assertInstanceOf(ShowParseTree::class, $show->statement);
        self::assertNull($show->shape());
        self::assertCount(1, $show->facts->diagnostics);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PARSE_TREE SELECT a FROM t', (new Semantics(Dialect::MySql))->analyze('SHOW PARSE_TREE SELECT a FROM t')->toString());
    }
}
