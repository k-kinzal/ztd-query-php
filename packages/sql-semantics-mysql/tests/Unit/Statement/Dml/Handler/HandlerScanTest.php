<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(HandlerScan::class)]
#[Medium]
final class HandlerScanTest extends TestCase
{
    public function testDeriveStatementRecordsOpenRows(): void
    {
        $operation = (new Inspection())->inspect('HANDLER h READ NEXT LIMIT 2');

        self::assertNotNull($operation->facts->output);
        self::assertFalse($operation->facts->output->shape->complete());
    }

    public function testRenderWritesTheRead(): void
    {
        self::assertSame('HANDLER h READ NEXT WHERE a > 1 LIMIT 1', (new Inspection('mysql-5.6.51'))->inspect('handler h read next where a > 1 limit 1')->toString());
    }
}
