<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Notify;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(Notify::class)]
#[Medium]
final class NotifyTest extends TestCase
{
    public function testDeriveStatementAcceptsAPayloadBelowTheLimit(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql))->analyze("NOTIFY c, '" . str_repeat('x', 7999) . "'")->facts->diagnostics);
    }

    public function testDeriveStatementReportsAPayloadAtTheLimit(): void
    {
        self::assertSame(['payload string too long'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze("NOTIFY c, '" . str_repeat('é', 4000) . "'")->facts->diagnostics));
    }

    public function testRenderWritesThePayloadAfterAComma(): void
    {
        self::assertSame(["NOTIFY c, ''", 'NOTIFY "C"'], [(new Semantics(Dialect::PostgreSql))->analyze("NOTIFY c, ''")->toString(), (new Semantics(Dialect::PostgreSql))->analyze('NOTIFY "C"')->toString()]);
    }
}
