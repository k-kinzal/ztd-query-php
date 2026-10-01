<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Reference;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Reference\CandidateColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CandidateColumnTest extends TestCase
{
    public function testCandidateOwnershipDoesNotInventADeclaration(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, catalog: false);
        $expression = $statement->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $expression);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\CandidateColumn::class, $expression->binding);
        self::assertSame([$statement->tables[0]], $expression->binding->relations);
        self::assertNull($statement->tables[0]->declaration);
    }
}
