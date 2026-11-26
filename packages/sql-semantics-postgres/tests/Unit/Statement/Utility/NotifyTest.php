<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Notify;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(Notify::class)]
#[Medium]
final class NotifyTest extends TestCase
{
    public function testDeriveStatementAcceptsAPayloadBelowTheLimit(): void
    {
        self::assertSame([], (new Inspection())->diagnose("NOTIFY c, '" . str_repeat('x', 7999) . "'")->facts->diagnostics);
    }

    public function testDeriveStatementReportsAPayloadAtTheLimit(): void
    {
        self::assertSame(['payload string too long'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose("NOTIFY c, '" . str_repeat('é', 4000) . "'")->facts->diagnostics));
    }

    public function testRenderWritesThePayloadAfterAComma(): void
    {
        self::assertSame(["NOTIFY c, ''", 'NOTIFY "C"'], [(new Inspection())->diagnose("NOTIFY c, ''")->toString(), (new Inspection())->diagnose('NOTIFY "C"')->toString()]);
    }
}
