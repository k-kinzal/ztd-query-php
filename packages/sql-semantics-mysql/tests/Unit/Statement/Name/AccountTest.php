<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Small]
final class AccountTest extends TestCase
{
    public function testBothKindsOfAccountImplementTheInterface(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $nodes = $platform->parser($profile)->parse('DROP USER bob@localhost, CURRENT_USER()')->find('user');
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $written = $lowering->users->account($nodes[0]);
        $current = $lowering->users->account($nodes[1]);

        self::assertInstanceOf(AccountName::class, $written);
        self::assertInstanceOf(CurrentUser::class, $current);
        self::assertContainsOnlyInstancesOf(Account::class, [new AccountName(new Name('bob')), new CurrentUser()]);
    }
}
