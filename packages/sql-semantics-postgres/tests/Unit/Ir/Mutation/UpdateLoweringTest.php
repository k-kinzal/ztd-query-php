<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\UpdateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(UpdateLowering::class)]
#[Medium]
final class UpdateLoweringTest extends TestCase
{
    public function testLowerResolvesBothRolesInTheSameSlotLayout(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.t (a INTEGER, b INTEGER)');
        $query = $inspection->inspect('UPDATE public.t SET a=b WHERE b<2', [$table]);
        self::assertInstanceOf(Update::class, $query->statement);

        $ir = (new UpdateLowering())->lower($query->binding(), $query->statement);
        $assignment = $ir->assignments[0];
        self::assertInstanceOf(ColumnReference::class, $assignment->value);
        self::assertSame('a', $ir->target->columns[$assignment->target->slot]->value);
        self::assertSame('b', $ir->target->columns[$assignment->value->slot]->value);
    }

    public function testValuePreservesEmptyTextInItsAssignedContext(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.t (a pg_catalog.text)');
        $query = $inspection->inspect("UPDATE public.t SET a = ''", [$table]);
        self::assertInstanceOf(Update::class, $query->statement);
        $assignment = $query->statement->assignments[0];
        self::assertInstanceOf(Assignment::class, $assignment);
        $binding = new SourceBinding($query->binding(), $query->statement->target);

        $value = (new UpdateLowering())->value($assignment->value, $binding, true, new TextColumnReference(0));

        self::assertInstanceOf(TextLiteral::class, $value);
        self::assertSame('', $value->value);
        self::assertInstanceOf(TextColumnReference::class, $binding->writeReference('a'));
    }
}
