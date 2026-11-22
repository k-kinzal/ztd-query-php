<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Help::class)]
#[Medium]
final class HelpTest extends TestCase
{
    public function testDeriveStatementLeavesTheShapeOpen(): void
    {
        $help = (new Inspection())->inspect('HELP contents');
        self::assertInstanceOf(Help::class, $help->statement);
        self::assertFalse($help->shape()?->complete());
    }

    public function testRenderWritesTheTopic(): void
    {
        self::assertSame('HELP `SHOW TABLES`', (new Inspection('mysql-5.6.51'))->inspect("help 'SHOW TABLES'")->toString());
    }
}
