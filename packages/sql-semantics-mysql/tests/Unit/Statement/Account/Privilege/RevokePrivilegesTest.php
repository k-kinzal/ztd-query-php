<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;

#[CoversClass(RevokePrivileges::class)]
#[Medium]
final class RevokePrivilegesTest extends TestCase
{
    public function testDeriveStatementAcceptsAnAbsentTable(): void
    {
        $revoke = (new Semantics(Dialect::MySql))->analyze('REVOKE SELECT ON t FROM u', []);

        self::assertInstanceOf(RevokePrivileges::class, $revoke->statement);
        self::assertInstanceOf(AbsentGrantTable::class, $revoke->facts->relation($revoke->statement->level)->table);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS ALL ON PROCEDURE p FROM u IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke if exists all privileges on procedure p from u ignore unknown user')->toString());
    }
}
