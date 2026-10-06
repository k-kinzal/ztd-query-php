<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeAll;

#[CoversClass(RevokeAll::class)]
#[Medium]
final class RevokeAllTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('REVOKE ALL, GRANT OPTION FROM u')->facts->diagnostics);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS ALL, GRANT OPTION FROM u IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke if exists all privileges, grant option from u ignore unknown user')->toString());
    }
}
