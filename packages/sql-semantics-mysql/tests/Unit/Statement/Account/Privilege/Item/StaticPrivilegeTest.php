<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;

#[CoversClass(StaticPrivilege::class)]
#[Medium]
final class StaticPrivilegeTest extends TestCase
{
    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('GRANT INSERT (a, `b c`), REFERENCES ON t TO u', (new Semantics(Dialect::MySql))->analyze('grant insert (a, `b c`), references on t to u')->toString());
    }
}
