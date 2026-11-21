<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\SetRole;

#[CoversClass(SetRole::class)]
#[Medium]
final class SetRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics())->analyze('SET ROLE DEFAULT')->facts->diagnostics);
    }

    public function testRenderWritesTheSelection(): void
    {
        self::assertSame('SET ROLE NONE', (new Semantics())->analyze('set role none')->toString());
    }
}
