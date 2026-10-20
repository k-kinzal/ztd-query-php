<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole;

#[CoversClass(RefusedCreateRole::class)]
#[Small]
final class RefusedCreateRoleTest extends TestCase
{
    public function testMessageDescribesTheFixedAccountDomain(): void
    {
        $request = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(''))]);
        self::assertSame('CREATE ROLE cannot define an anonymous account.', (new RefusedCreateRole($request))->message());
    }
}
