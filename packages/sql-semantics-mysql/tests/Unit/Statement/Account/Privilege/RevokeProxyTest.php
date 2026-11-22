<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RevokeProxy::class)]
#[Medium]
final class RevokeProxyTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('REVOKE PROXY ON a FROM b')->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS PROXY ON a FROM b IGNORE UNKNOWN USER', (new Inspection())->inspect('revoke if exists proxy on a from b ignore unknown user')->toString());
    }
}
