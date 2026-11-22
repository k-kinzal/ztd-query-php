<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Unlisten;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Unlisten::class)]
#[Medium]
final class UnlistenTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('UNLISTEN c');
        self::assertSame([[], null], [$operation->facts->diagnostics, $operation->facts->output]);
    }

    public function testRenderWritesTheChannelOrTheStar(): void
    {
        self::assertSame(['UNLISTEN c', 'UNLISTEN *'], [(new Inspection())->inspect('UNLISTEN C')->toString(), (new Inspection())->inspect('UNLISTEN *')->toString()]);
    }
}
