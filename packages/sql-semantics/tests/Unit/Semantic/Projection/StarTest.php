<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Projection;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Projection\Star::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class StarTest extends TestCase
{
    public function testToStringAndExpansionKeepQualifiedOccurrence(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.* FROM bar a, bar b');
        $star = $statement->fields()->items[0];
        self::assertInstanceOf(\SqlSemantics\Semantic\Projection\Star::class, $star);
        self::assertSame('b.*', $star->toString());
        self::assertCount(2, $statement->fields()->outputs());
        $column = $statement->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $column);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\ResolvedColumn::class, $column->binding);
        self::assertSame($statement->tables[1], $column->binding->relation);
    }
}
