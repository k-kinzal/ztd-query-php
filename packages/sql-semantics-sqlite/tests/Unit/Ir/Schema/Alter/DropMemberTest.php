<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropMember;

#[CoversClass(DropMember::class)]
#[Small]
final class DropMemberTest extends TestCase
{
    public function testGeneratedAndRestrictedAreIndependentRoles(): void
    {
        $name = new Identifier('computed');
        $member = new DropMember($name, true, true);

        self::assertSame($name, $member->name);
        self::assertTrue($member->generated);
        self::assertTrue($member->protected);
    }
}
