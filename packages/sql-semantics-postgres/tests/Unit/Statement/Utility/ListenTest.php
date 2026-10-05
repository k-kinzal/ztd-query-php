<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Listen;

#[CoversClass(Listen::class)]
#[Medium]
final class ListenTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('LISTEN jobs');
        self::assertSame([[], null], [$operation->facts->diagnostics, $operation->facts->output]);
    }

    public function testRenderQuotesWhatFoldingWouldChange(): void
    {
        self::assertSame(['LISTEN "Jobs"', 'LISTEN "select"', 'LISTEN jobs'], [(new Semantics(Dialect::PostgreSql))->analyze('LISTEN "Jobs"')->toString(), (new Semantics(Dialect::PostgreSql))->analyze('LISTEN "select"')->toString(), (new Semantics(Dialect::PostgreSql))->analyze('LISTEN Jobs')->toString()]);
    }
}
