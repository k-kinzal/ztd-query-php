<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;

#[CoversClass(DropUser::class)]
#[Medium]
final class DropUserTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics())->inspect('DROP USER u')->facts->diagnostics);
    }

    public function testRenderWritesTheAccounts(): void
    {
        self::assertSame('DROP USER a, CURRENT_USER()', (new Semantics('mysql-5.6.51'))->inspect('drop user a, current_user()')->toString());
    }
}
