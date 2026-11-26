<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(HandlerIndexRead::class)]
#[Medium]
final class HandlerIndexReadTest extends TestCase
{
    public function testDeriveStatementRecordsOpenRows(): void
    {
        $operation = (new Inspection())->inspect('HANDLER h READ i LAST');

        self::assertNotNull($operation->facts->output);
    }

    public function testRenderWritesTheRead(): void
    {
        self::assertSame('HANDLER h READ i PREV LIMIT 3', (new Inspection())->inspect('handler h read i prev limit 3')->toString());
    }
}
