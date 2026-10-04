<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection;

#[CoversClass(RoleSelection::class)]
#[Medium]
final class RoleSelectionTest extends TestCase
{
    public function testRenderWritesEverySelection(): void
    {
        self::assertSame('SET ROLE ALL', (new Semantics(Dialect::MySql))->analyze('set role all')->toString());
        self::assertSame('SET ROLE ALL EXCEPT r1, r2', (new Semantics(Dialect::MySql))->analyze('set role all except r1, r2')->toString());
        self::assertSame('SET ROLE DEFAULT', (new Semantics(Dialect::MySql))->analyze('set role default')->toString());
    }
}
