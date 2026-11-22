<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UpdateLowering::class)]
#[Medium]
final class UpdateLoweringTest extends TestCase
{
    public function testLowerResolvesBothRolesInTheSameSlotLayout(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (a INTEGER, b INTEGER)');
        $query = $inspection->inspect('UPDATE app.t SET a=b WHERE b<2', [$table]);
        self::assertInstanceOf(Update::class, $query->statement);

        $ir = (new UpdateLowering())->lower($query->binding(), $query->statement);
        $assignment = $ir->assignments[0];
        self::assertInstanceOf(ColumnReference::class, $assignment->value);
        self::assertSame('a', $ir->target->columns[$assignment->target->slot]->value);
        self::assertSame('b', $ir->target->columns[$assignment->value->slot]->value);
    }
}
