<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Reference;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Reference\AmbiguousColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class AmbiguousColumnTest extends TestCase
{
    public function testSelfJoinDoesNotCollapseDistinctOccurrences(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar a, bar b');
        $expression = $statement->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $expression);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\AmbiguousColumn::class, $expression->binding);
        self::assertCount(2, $expression->binding->matches);
        self::assertNotSame($expression->binding->matches[0]->relation, $expression->binding->matches[1]->relation);
        self::assertSame($expression->binding->matches[0]->column, $expression->binding->matches[1]->column);
    }
}
