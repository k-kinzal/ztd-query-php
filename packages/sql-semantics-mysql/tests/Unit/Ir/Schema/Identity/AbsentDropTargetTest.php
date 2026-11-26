<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\AbsentDropTarget;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(AbsentDropTarget::class)]
#[Small]
final class AbsentDropTargetTest extends TestCase
{
    public function testQuotedPreservesTheAbsoluteAbsentIdentity(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('lost`table'));
        $target = new AbsentDropTarget($name);

        self::assertSame($name, $target->name);
        self::assertSame('`app`.`lost``table`', $target->quoted());
    }
}
