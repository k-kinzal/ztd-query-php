<?php

declare(strict_types=1);

namespace Tests\Unit\Account;

use MySqlMemory\Account\Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Catalog::class)]
#[Small]
final class CatalogTest extends TestCase
{
    public function testRegisteredKnowsTheDynamicPrivilegesInAnyCase(): void
    {
        self::assertTrue((new Catalog())->registered('backup_admin'));
        self::assertFalse((new Catalog())->registered('FOO_BAR'));
    }

    public function testOrderedAnswersThePrivilegesInTheOrderOfShowGrants(): void
    {
        self::assertSame(['SELECT', 'RELOAD', 'DROP ROLE'], (new Catalog())->ordered(['DROP ROLE' => true, 'RELOAD' => true, 'SELECT' => true]));
    }
}
