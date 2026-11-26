<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveColumn;
use SqlSemantics\Platform\Sqlite\Ir\Rows\RecursiveDefinition;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;

#[CoversClass(RecursiveDefinition::class)]
#[Small]
final class RecursiveDefinitionTest extends TestCase
{
    public function testScopedExpressionsAreDetachedFromTheInput(): void
    {
        $initial = new IntegerLiteral('1');
        $anchor = new ValuesQuery([new ValueRow([$initial])]);
        $output = new RecursiveColumn(new ColumnReference(0));
        $step = [&$output];
        $definition = new RecursiveDefinition($anchor, $step, unionAll: true);
        $output = new RecursiveColumn(new IntegerLiteral('2'));

        self::assertEquals([[new ColumnReference(0), 0, 1], [$initial, 0, 0]], $definition->scopedExpressions());
        self::assertNotSame($output, $definition->step[0]);
    }

    public function testRejectsAFilterThatReferencesAnAbsentSlot(): void
    {
        $this->expectExceptionMessage('Column slot must belong to the statement source at a visible occurrence.');

        new RecursiveDefinition(
            new ValuesQuery([new ValueRow([new IntegerLiteral('0')])]),
            [new RecursiveColumn(new ColumnReference(0))],
            new ColumnReference(1),
        );
    }
}
