<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;

#[CoversClass(HandlerClose::class)]
#[Medium]
final class HandlerCloseTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h CLOSE');

        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesTheHandler(): void
    {
        self::assertSame('HANDLER h CLOSE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('handler h close')->toString());
    }
}
