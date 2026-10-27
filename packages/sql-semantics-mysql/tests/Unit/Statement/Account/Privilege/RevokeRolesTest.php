<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles;

#[CoversClass(RevokeRoles::class)]
#[Medium]
final class RevokeRolesTest extends TestCase
{
    public function testDeriveStatementNeedsNoExpressionBindings(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('REVOKE r FROM u')->diagnostics());
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS r1, r2 FROM u IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke if exists r1, r2 from u ignore unknown user')->toString());
    }
}
