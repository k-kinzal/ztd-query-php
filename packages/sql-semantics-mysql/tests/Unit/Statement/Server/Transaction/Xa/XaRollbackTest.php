<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback;

#[CoversClass(XaRollback::class)]
#[Medium]
final class XaRollbackTest extends TestCase
{
    public function testRenderWritesTheIdentifier(): void
    {
        self::assertSame("XA ROLLBACK 'g'", (new Semantics(Dialect::MySql))->analyze("xa rollback 'g'")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("XA ROLLBACK 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
