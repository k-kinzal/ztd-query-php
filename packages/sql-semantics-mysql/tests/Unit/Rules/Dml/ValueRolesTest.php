<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\ValueRoles;

#[CoversClass(ValueRoles::class)]
#[Medium]
final class ValueRolesTest extends TestCase
{
    public function testCheckKeepsDormantOperandsInsideUnusedWriteClauses(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t VALUES ROW(1) ORDER BY ABS()');
        (new ValueRoles())->check($operation);
        self::assertSame('INSERT INTO t VALUES ROW(1) ORDER BY ABS()', $operation->toString());
    }
}
