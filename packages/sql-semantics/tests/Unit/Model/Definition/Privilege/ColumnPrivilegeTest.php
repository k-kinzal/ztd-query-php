<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ColumnPrivilege::class)]
#[Medium]
final class ColumnPrivilegeTest extends TestCase
{
    public function testKeepsTheColumnsInRequestOrder(): void
    {
        $privilege = new ColumnPrivilege(StaticPrivilege::References, ['b', 'a']);
        self::assertSame(StaticPrivilege::References, $privilege->privilege);
        self::assertSame(['b', 'a'], $privilege->columns);
    }

    public function testRejectsAPrivilegeWithoutColumnScope(): void
    {
        $this->expectException(InvalidStructure::class);
        new ColumnPrivilege(StaticPrivilege::Index, ['a']);
    }
}
