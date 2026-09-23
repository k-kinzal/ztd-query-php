<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(NamedRole::class)]
#[Medium]
final class NamedRoleTest extends TestCase
{
    public function testRoleNamesRemainCaseSensitiveAndDistinctFromSpecialWords(): void
    {
        self::assertSame('CURRENT_USER', (new NamedRole('CURRENT_USER'))->name);
        self::assertSame('Public', (new NamedRole('Public'))->name);
        self::assertSame('None', (new NamedRole('None'))->name);
    }

    #[TestWith([''])]
    #[TestWith(['public'])]
    #[TestWith(['none'])]
    public function testNamedRolesRejectReservedOrEmptyNames(string $name): void
    {
        $this->expectException(InvalidStructure::class);
        new NamedRole($name);
    }

}
