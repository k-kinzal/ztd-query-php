<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Projection;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Projection\OutputReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OutputReferenceTest extends TestCase
{
    public function testToStringKeepsOrderByAttachedToAnOutput(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo + 1 AS n FROM bar ORDER BY n');
        $reference = $statement->orderBy[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Projection\OutputReference::class, $reference);
        self::assertSame($statement->field('n'), $reference->field);
        self::assertSame('n', $reference->toString());
    }
}
