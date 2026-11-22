<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use Tests\Support\Inspection;

#[CoversClass(GrantedRole::class)]
#[Medium]
final class GrantedRoleTest extends TestCase
{
    public function testRenderWritesTheRole(): void
    {
        self::assertSame('REVOKE r@h FROM u', (new Inspection())->inspect("revoke 'r'@'h' from u")->toString());
    }
}
