<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\CreateRole;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateRole::class)]
#[Medium]
final class CreateRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('CREATE ROLE r')->facts->diagnostics);
    }

    public function testRenderWritesTheRoles(): void
    {
        self::assertSame('CREATE ROLE IF NOT EXISTS r1, r2@h', (new Inspection())->inspect('create role if not exists r1, r2@h')->toString());
    }
}
