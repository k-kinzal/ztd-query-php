<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\SetDefaultRole;

#[CoversClass(SetDefaultRole::class)]
#[Medium]
final class SetDefaultRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics())->analyze('SET DEFAULT ROLE ALL TO u')->facts->diagnostics);
    }

    public function testRenderWritesTheRolesAndAccounts(): void
    {
        self::assertSame('SET DEFAULT ROLE r1, r2 TO u, v@h', (new Semantics())->analyze('set default role r1, r2 to u, v@h')->toString());
    }
}
