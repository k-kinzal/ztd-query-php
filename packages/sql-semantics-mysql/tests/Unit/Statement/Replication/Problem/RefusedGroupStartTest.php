<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedGroupStart;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart;

#[CoversClass(RefusedGroupStart::class)]
#[Small]
final class RefusedGroupStartTest extends TestCase
{
    public function testConditionReportsTheMissingUser(): void
    {
        $request = new DeferredGroupStart([new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('p'))]);
        self::assertSame(ReplicationError::GroupUserMissing, $request->problem()->condition()->error);
    }

    public function testMessageDescribesTheCredentialCondition(): void
    {
        $request = new DeferredGroupStart([new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('p'))]);
        self::assertSame($request->problem()->condition()->message(), $request->problem()->message());
    }
}
