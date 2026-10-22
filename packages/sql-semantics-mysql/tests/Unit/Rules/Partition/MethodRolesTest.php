<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Partition\MethodRoles;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(MethodRoles::class)]
#[Small]
final class MethodRolesTest extends TestCase
{
    public function testDeferredFindsRetainedSubpartitionOperands(): void
    {
        $row = new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
        $clause = new M\Partition\PartitionClause(new M\Partition\Method\KeyMethod(false, null, []), null, new M\Partition\Subpartitioning(new M\Partition\Method\DeferredMethod($row), null), []);
        self::assertTrue((new MethodRoles())->deferred($clause));
        self::assertFalse((new MethodRoles())->deferred(null));
    }

    public function testCommandsKeepsOrdinaryPartitionMethodsSeparate(): void
    {
        $clause = new M\Partition\PartitionClause(new M\Partition\Method\HashMethod(false, new M\Literal\NumberLiteral('1')), null, null, []);
        self::assertFalse((new MethodRoles())->commands([new M\Alter\Partition\PartitionBy($clause)]));
    }
}
