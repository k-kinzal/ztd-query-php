<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DynamicPrivilege::class)]
#[Medium]
final class DynamicPrivilegeTest extends TestCase
{
    public function testRenderWritesTheNameAndColumns(): void
    {
        self::assertSame('GRANT XA_RECOVER_ADMIN (c) ON *.* TO u', (new Inspection())->inspect("grant 'XA_RECOVER_ADMIN' (c) on *.* to u")->toString());
    }
}
