<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\PrivilegeChange;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantLevel;

#[CoversNothing]
#[Small]
final class PrivilegeChangeTest extends TestCase
{
    public function testImmediateAndDeferredGrantsShareTheirEnclosingBodyRestriction(): void
    {
        self::assertContains(PrivilegeChange::class, class_implements(GrantPrivileges::class));
        self::assertContains(PrivilegeChange::class, class_implements(DeferredGrantAs::class));
        self::assertContains(PrivilegeChange::class, class_implements(DeferredGrantLevel::class));
    }
}
