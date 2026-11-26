<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;

#[CoversClass(PrivilegeLists::class)]
#[Medium]
final class PrivilegeListsTest extends TestCase
{
    public function testPrivilegesAcceptsAnEmptyList(): void
    {
        self::assertSame([], (new PrivilegeLists())->privileges([]));
    }

    public function testPrivilegesRejectsAForeignItem(): void
    {
        $this->expectExceptionMessage('The privileges of a grant are a list of privileges.');
        (new PrivilegeLists())->privileges([new Name('select')]);
    }

    public function testRolesRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('A role grant names at least one role.');
        (new PrivilegeLists())->roles([]);
    }

    public function testWriteSpellsAnEmptyListAsAll(): void
    {
        self::assertSame('REVOKE ALL ON TABLE t FROM joe', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('REVOKE ALL PRIVILEGES ON t FROM joe')->toString());
    }

    public function testLegacyOnlyDistinguishesAllFromAnInertRequest(): void
    {
        $lists = new PrivilegeLists();
        $legacy = new Privilege(new Name('rule'));
        self::assertTrue($lists->legacyOnly([$legacy, $legacy]));
        self::assertFalse($lists->legacyOnly([]));
        self::assertFalse($lists->legacyOnly([$legacy, new Privilege(new Name('usage'))]));
    }
}
