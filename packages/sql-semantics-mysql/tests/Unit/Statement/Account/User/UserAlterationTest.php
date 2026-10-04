<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;

#[CoversNothing]
#[Medium]
final class UserAlterationTest extends TestCase
{
    public function testBothKindsOfEntryImplementTheInterface(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER USER u, v DROP 2 FACTOR');

        self::assertInstanceOf(AlterUser::class, $alter->statement);
        self::assertContainsOnlyInstancesOf(UserAlteration::class, $alter->statement->users);
        self::assertInstanceOf(UserSpecification::class, $alter->statement->users[0]);
        self::assertInstanceOf(FactorChange::class, $alter->statement->users[1]);
    }
}
