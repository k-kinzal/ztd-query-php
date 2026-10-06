<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

#[CoversClass(RoleSpecKind::class)]
#[Small]
final class RoleSpecKindTest extends TestCase
{
    public function testCasesMirrorTheRoleSpecificationKindsOfTheServer(): void
    {
        self::assertSame(['Named', 'Everyone', 'CurrentRole', 'CurrentUser', 'SessionUser'], array_map(static fn (RoleSpecKind $kind): string => $kind->name, RoleSpecKind::cases()));
    }
}
