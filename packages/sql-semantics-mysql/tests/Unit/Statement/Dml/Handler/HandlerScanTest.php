<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;

#[CoversClass(HandlerScan::class)]
#[Medium]
final class HandlerScanTest extends TestCase
{
    public function testDeriveStatementRecordsOpenRows(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ NEXT LIMIT 2');

        self::assertNotNull($operation->facts->output);
        self::assertFalse($operation->facts->output->shape->complete());
    }

    public function testRenderWritesTheRead(): void
    {
        self::assertSame('HANDLER h READ NEXT WHERE a > 1 LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('handler h read next where a > 1 limit 1')->toString());
    }
}
