<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RefusedColumnLevel;

#[CoversClass(RefusedColumnLevel::class)]
#[Small]
final class RefusedColumnLevelTest extends TestCase
{
    public function testAColumnTargetRefusalCannotDescribeAWholePrivilege(): void
    {
        $this->expectExceptionMessage('A refused column target needs a column privilege.');
        new RefusedColumnLevel(new StaticPrivilege(PrivilegeKind::Select), new GlobalLevel());
    }

    public function testMessageDescribesOnlyTheCompletedTarget(): void
    {
        $column = new \SqlSemantics\Statement\Identifier\Name('a');
        $value = new RefusedColumnLevel(new StaticPrivilege(PrivilegeKind::Select, [$column]), new GlobalLevel());
        self::assertSame('A column privilege requires an individual table target.', $value->message());
        self::assertSame([$column], $value->privilege->columns);
        self::assertNull($value->program);
    }
}
