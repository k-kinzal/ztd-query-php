<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet;

#[CoversClass(RoleSet::class)]
#[Small]
final class RoleSetTest extends TestCase
{
    public function testCasesNameTheSelections(): void
    {
        self::assertSame(['Named', 'None', 'Default', 'All'], array_column(RoleSet::cases(), 'name'));
    }
}
