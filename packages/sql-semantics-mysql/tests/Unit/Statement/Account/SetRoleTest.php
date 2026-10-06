<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\SetRole;

#[CoversClass(SetRole::class)]
#[Medium]
final class SetRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('SET ROLE DEFAULT')->facts->diagnostics);
    }

    public function testRenderWritesTheSelection(): void
    {
        self::assertSame('SET ROLE NONE', (new Semantics(Dialect::MySql))->analyze('set role none')->toString());
    }
}
