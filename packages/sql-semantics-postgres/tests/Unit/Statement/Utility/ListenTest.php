<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Listen;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Listen::class)]
#[Medium]
final class ListenTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('LISTEN jobs');
        self::assertSame([[], null], [$operation->facts->diagnostics, $operation->facts->output]);
    }

    public function testRenderQuotesWhatFoldingWouldChange(): void
    {
        self::assertSame(['LISTEN "Jobs"', 'LISTEN "select"', 'LISTEN jobs'], [(new Inspection())->inspect('LISTEN "Jobs"')->toString(), (new Inspection())->inspect('LISTEN "select"')->toString(), (new Inspection())->inspect('LISTEN Jobs')->toString()]);
    }
}
