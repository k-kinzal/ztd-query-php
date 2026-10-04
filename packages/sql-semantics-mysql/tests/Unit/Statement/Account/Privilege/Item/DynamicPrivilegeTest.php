<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;

#[CoversClass(DynamicPrivilege::class)]
#[Medium]
final class DynamicPrivilegeTest extends TestCase
{
    public function testRenderWritesTheNameAndColumns(): void
    {
        self::assertSame('GRANT XA_RECOVER_ADMIN (c) ON *.* TO u', (new Semantics(Dialect::MySql))->analyze("grant 'XA_RECOVER_ADMIN' (c) on *.* to u")->toString());
    }
}
