<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlterDefaultRole::class)]
#[Medium]
final class AlterDefaultRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('ALTER USER u DEFAULT ROLE NONE')->facts->diagnostics);
    }

    public function testRenderWritesTheRoles(): void
    {
        self::assertSame('ALTER USER IF EXISTS CURRENT_USER DEFAULT ROLE r1, r2', (new Inspection())->inspect('alter user if exists current_user default role r1, r2')->toString());
    }
}
