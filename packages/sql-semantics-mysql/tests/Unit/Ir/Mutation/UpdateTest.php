<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Mutation\Update;
use SqlSemantics\Platform\MySql\Ir\Mutation\UpdateAssignment;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(Update::class)]
#[Small]
final class UpdateTest extends TestCase
{
    public function testToStringRetainsAReferenceToTheSameTargetRow(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('a'), new Identifier('b')]);
        $assignment = new UpdateAssignment(new ColumnReference(0), new ColumnReference(1));
        $update = new Update($target, [$assignment], new BooleanLiteral(false));

        self::assertSame('UPDATE `app`.`t` AS `__ztd_source` SET `a` = `__ztd_source`.`b` WHERE FALSE', $update->toString());
    }

    public function testProfileIdIdentifiesNativeReleases(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('a')]);
        $update = new Update($target, [new UpdateAssignment(new ColumnReference(0), new NullLiteral())]);

        self::assertStringStartsWith('mysql-9.1.0/', $update->profileId());
    }

    public function testRejectsAWriteOutsideTheDeclaredSlots(): void
    {
        $target = new TableSource(new Identifier('app'), new Identifier('t'), [new Identifier('a')]);
        $this->expectExceptionMessage('write slot must belong to the target');

        new Update($target, [new UpdateAssignment(new ColumnReference(1), new NullLiteral())]);
    }
}
