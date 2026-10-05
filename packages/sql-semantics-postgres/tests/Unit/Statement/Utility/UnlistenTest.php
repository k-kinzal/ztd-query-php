<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Unlisten;

#[CoversClass(Unlisten::class)]
#[Medium]
final class UnlistenTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('UNLISTEN c');
        self::assertSame([[], null], [$operation->facts->diagnostics, $operation->facts->output]);
    }

    public function testRenderWritesTheChannelOrTheStar(): void
    {
        self::assertSame(['UNLISTEN c', 'UNLISTEN *'], [(new Semantics(Dialect::PostgreSql))->analyze('UNLISTEN C')->toString(), (new Semantics(Dialect::PostgreSql))->analyze('UNLISTEN *')->toString()]);
    }
}
