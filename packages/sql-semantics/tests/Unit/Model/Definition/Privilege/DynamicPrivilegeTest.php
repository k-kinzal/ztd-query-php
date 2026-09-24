<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DynamicPrivilege::class)]
#[Medium]
final class DynamicPrivilegeTest extends TestCase
{
    public function testKeepsTheName(): void
    {
        self::assertSame('BACKUP_ADMIN', (new DynamicPrivilege('BACKUP_ADMIN'))->name);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DynamicPrivilege('');
    }
}
