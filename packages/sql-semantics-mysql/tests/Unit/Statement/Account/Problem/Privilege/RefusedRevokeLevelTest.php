<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Privilege\RefusedRevokeLevel;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RefusedRevokeLevel::class)]
#[Small]
final class RefusedRevokeLevelTest extends TestCase
{
    public function testMessageExplainsTheRetainedTargetCondition(): void
    {
        $request = new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        $problem = new RefusedRevokeLevel($request);
        self::assertSame('The routine revocation does not name a routine object.', $problem->message());
        self::assertSame($request, $problem->request);
    }
}
