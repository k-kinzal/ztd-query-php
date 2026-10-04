<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists::class)]
#[Medium]
final class PrivilegeListsTest extends TestCase
{
    public function testPrivilegesAcceptsAnEmptyList(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists())->privileges([]));
    }

    public function testPrivilegesRejectsAForeignItem(): void
    {
        $this->expectExceptionMessage('The privileges of a grant are a list of privileges.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists())->privileges([new \SqlSemantics\Statement\Identifier\Name('select')]);
    }

    public function testRolesRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('A role grant names at least one role.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists())->roles([]);
    }

    public function testWriteSpellsAnEmptyListAsAll(): void
    {
        self::assertSame('REVOKE ALL ON TABLE t FROM joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE ALL PRIVILEGES ON t FROM joe')->toString());
    }
}
