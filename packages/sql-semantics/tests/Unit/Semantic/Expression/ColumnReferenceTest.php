<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\ColumnReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ColumnReferenceTest extends TestCase
{
    public function testToStringDoesNotLoseItsOccurrenceBinding(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar b');
        $column = $statement->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $column);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\ResolvedColumn::class, $column->binding);
        self::assertSame($statement->tables[0], $column->binding->relation);
        self::assertSame('b.foo', $column->toString());
    }
}
