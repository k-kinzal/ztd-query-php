<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(HandlerClose::class)]
#[Medium]
final class HandlerCloseTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('HANDLER h CLOSE');

        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesTheHandler(): void
    {
        self::assertSame('HANDLER h CLOSE', (new Inspection('mysql-5.7.44'))->inspect('handler h close')->toString());
    }
}
