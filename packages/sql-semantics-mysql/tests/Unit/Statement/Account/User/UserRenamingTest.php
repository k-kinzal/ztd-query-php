<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;

#[CoversClass(UserRenaming::class)]
#[Medium]
final class UserRenamingTest extends TestCase
{
    public function testRenderWritesThePair(): void
    {
        self::assertSame('RENAME USER CURRENT_USER() TO b', (new Semantics(Dialect::MySql))->analyze('rename user current_user() to b')->toString());
    }
}
