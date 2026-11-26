<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropUser::class)]
#[Medium]
final class DropUserTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('DROP USER u')->facts->diagnostics);
    }

    public function testRenderWritesTheAccounts(): void
    {
        self::assertSame('DROP USER a, CURRENT_USER()', (new Inspection('mysql-5.6.51'))->inspect('drop user a, current_user()')->toString());
    }
}
