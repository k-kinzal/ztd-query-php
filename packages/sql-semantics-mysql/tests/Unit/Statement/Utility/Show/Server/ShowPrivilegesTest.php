<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPrivileges;

#[CoversClass(ShowPrivileges::class)]
#[Medium]
final class ShowPrivilegesTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW PRIVILEGES');
        self::assertInstanceOf(ShowPrivileges::class, $show->statement);
        self::assertSame('Privilege', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PRIVILEGES', (new Semantics(Dialect::MySql))->analyze('SHOW PRIVILEGES')->toString());
    }
}
