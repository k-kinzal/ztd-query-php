<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\DropRole;

#[CoversClass(DropRole::class)]
#[Medium]
final class DropRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('DROP ROLE r')->facts->diagnostics);
    }

    public function testRenderWritesTheRoles(): void
    {
        self::assertSame('DROP ROLE IF EXISTS r1, r2', (new Semantics(Dialect::MySql))->analyze('drop role if exists r1, r2')->toString());
    }
}
