<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;

#[CoversClass(XaEnd::class)]
#[Medium]
final class XaEndTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame("XA END 'g' SUSPEND", (new Semantics(Dialect::MySql))->analyze("xa end 'g' suspend")->toString());
    }

    public function testRenderWritesForMigrateOf56(): void
    {
        self::assertSame("XA END 'g' SUSPEND FOR MIGRATE", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("xa end 'g' suspend for migrate")->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("XA END 'g'");

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
