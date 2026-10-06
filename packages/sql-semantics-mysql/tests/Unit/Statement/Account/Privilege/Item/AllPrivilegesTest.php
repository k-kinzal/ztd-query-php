<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;

#[CoversClass(AllPrivileges::class)]
#[Medium]
final class AllPrivilegesTest extends TestCase
{
    public function testRenderWritesAll(): void
    {
        self::assertSame('GRANT ALL ON *.* TO u', (new Semantics(Dialect::MySql))->analyze('grant all privileges on *.* to u')->toString());
    }
}
