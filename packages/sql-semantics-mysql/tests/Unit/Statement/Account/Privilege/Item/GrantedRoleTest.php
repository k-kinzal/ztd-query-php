<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;

#[CoversClass(GrantedRole::class)]
#[Medium]
final class GrantedRoleTest extends TestCase
{
    public function testRenderWritesTheRole(): void
    {
        self::assertSame('REVOKE r@h FROM u', (new Semantics(Dialect::MySql))->analyze("revoke 'r'@'h' from u")->toString());
    }
}
