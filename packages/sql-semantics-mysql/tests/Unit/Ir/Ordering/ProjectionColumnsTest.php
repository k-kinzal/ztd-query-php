<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\ProjectionColumns;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ProjectionColumns::class)]
#[Medium]
final class ProjectionColumnsTest extends TestCase
{
    public function testLowerKeepsResolvedWildcardPositions(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (key_id INT, amount INT)');
        $operation = $inspection->inspect('SELECT e.amount AS before_fields, e.* FROM app.events e', [$table]);
        $bound = $operation->binding();
        $select = $bound->statement;
        self::assertInstanceOf(Select::class, $select);
        $source = new SourceBinding($bound, $select->from);
        $columns = (new ProjectionColumns())->lower($bound, $select, $source);
        self::assertSame(['before_fields', 'key_id', 'amount'], array_map(static fn ($column): string => $column->name->value, $columns));
    }
}
