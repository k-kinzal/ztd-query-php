<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\User\RefusedRenameUser;

#[CoversClass(RefusedRenameUser::class)]
#[Small]
final class RefusedRenameUserTest extends TestCase
{
    public function testMessageDescribesTheFixedAccountDomain(): void
    {
        $request = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser(), new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        self::assertSame('RENAME USER requires consistent source and destination identities throughout its ordered pairs.', (new RefusedRenameUser($request))->message());
    }
}
