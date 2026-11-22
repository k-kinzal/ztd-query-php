<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\DropRole;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropRole::class)]
#[Medium]
final class DropRoleTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('DROP ROLE r')->facts->diagnostics);
    }

    public function testRenderWritesTheRoles(): void
    {
        self::assertSame('DROP ROLE IF EXISTS r1, r2', (new Inspection())->inspect('drop role if exists r1, r2')->toString());
    }
}
