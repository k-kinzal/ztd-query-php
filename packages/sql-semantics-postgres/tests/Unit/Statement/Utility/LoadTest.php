<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Load;

#[CoversClass(Load::class)]
#[Medium]
final class LoadTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze("LOAD 'x'");
        self::assertSame([[], null], [$operation->facts->diagnostics, $operation->facts->output]);
    }

    public function testRenderWritesTheFileString(): void
    {
        self::assertSame("LOAD '\$libdir/plugins/a''b'", (new Semantics(Dialect::PostgreSql))->analyze("LOAD E'\$libdir/plugins/a\\'b'")->toString());
    }
}
