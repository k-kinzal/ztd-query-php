<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\TextUpdateAssignment;
use SqlSemantics\Platform\MySql\Ir\Mutation\UpdateAssignmentLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UpdateAssignmentLowering::class)]
#[Medium]
final class UpdateAssignmentLoweringTest extends TestCase
{
    public function testLowerKeepsCurrentRowSlotAndDeclaredNullability(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (source_value VARCHAR(12) COLLATE utf8mb4_bin, target_value VARCHAR(2) COLLATE utf8mb4_bin NOT NULL)');
        $query = $inspection->inspect('UPDATE app.t SET target_value = t.source_value', [$table]);
        self::assertInstanceOf(Update::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->tables[0]);
        $model = (new UpdateAssignmentLowering())->lower('target_value', $query->statement->assignments[0]->value, $binding);

        self::assertInstanceOf(TextUpdateAssignment::class, $model);
        self::assertInstanceOf(TextColumnReference::class, $model->value);
        self::assertSame(0, $model->target->slot);
        self::assertSame(1, $model->value->slot);
        $source = $binding->source();
        self::assertNotNull($source);
        $kind = $source->columnTypes[0];
        self::assertInstanceOf(Varchar::class, $kind);
        self::assertSame(2, $kind->length);
        self::assertFalse($kind->nullable);
    }

    public function testTextValuePreservesDecodedUnicodeAndPadding(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin)');
        $query = $inspection->inspect("UPDATE app.t SET label = ('é𐐷 ')", [$table]);
        self::assertInstanceOf(Update::class, $query->statement);
        $binding = new SourceBinding($query->binding(), $query->statement->tables[0]);
        $value = (new UpdateAssignmentLowering())->textValue($query->statement->assignments[0]->value, $binding);

        self::assertInstanceOf(TextLiteral::class, $value);
        self::assertSame('é𐐷 ', $value->value);
    }
}
