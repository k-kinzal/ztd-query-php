<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Update;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\UpdateAssignment;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Update::class)]
#[Small]
final class UpdateTest extends TestCase
{
    public function testToStringRetainsAReferenceToTheSameTargetRow(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a'), new Identifier('b')]);
        $assignment = new UpdateAssignment(new ColumnReference(0), new ColumnReference(1));
        $update = new Update($target, [$assignment], new BooleanLiteral(false));

        self::assertSame('UPDATE "public"."t" AS "__ztd_source" SET "a" = "__ztd_source"."b" WHERE FALSE', $update->toString());
    }

    public function testProfileIdIdentifiesNativeReleases(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a')]);
        $update = new Update($target, [new UpdateAssignment(new ColumnReference(0), new NullLiteral())]);

        self::assertStringStartsWith('pg-17.2/', $update->profileId());
    }

    public function testToStringReturnsTheNamedUpdatedTargetField(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a')]);
        $slot = new ColumnReference(0);
        $field = new ReturnedColumn($slot, new Identifier('new_a'));
        $update = new Update($target, [new UpdateAssignment($slot, new NullLiteral())], returning: [$field]);

        self::assertSame([$field], $update->returning);
        self::assertSame('UPDATE "public"."t" AS "__ztd_source" SET "a" = NULL RETURNING "a" AS "new_a"', $update->toString());
    }

    public function testRejectsAWriteOutsideTheDeclaredSlots(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a')]);
        $this->expectExceptionMessage('write slot must belong to the target');

        new Update($target, [new UpdateAssignment(new ColumnReference(1), new NullLiteral())]);
    }
}
