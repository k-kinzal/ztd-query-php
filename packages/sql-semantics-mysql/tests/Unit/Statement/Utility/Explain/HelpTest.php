<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;

#[CoversClass(Help::class)]
#[Medium]
final class HelpTest extends TestCase
{
    public function testDeriveStatementLeavesTheShapeOpen(): void
    {
        $help = (new Semantics(Dialect::MySql))->analyze('HELP contents');
        self::assertInstanceOf(Help::class, $help->statement);
        self::assertFalse($help->shape()?->complete());
    }

    public function testRenderWritesTheTopic(): void
    {
        self::assertSame('HELP `SHOW TABLES`', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("help 'SHOW TABLES'")->toString());
    }
}
