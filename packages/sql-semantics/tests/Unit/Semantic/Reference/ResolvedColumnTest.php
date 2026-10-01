<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Reference;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Reference\ResolvedColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ResolvedColumnTest extends TestCase
{
    public function testResolvedReferenceKeepsTheExactDeclarationObject(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        $reference = $statement->field('foo')->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $reference);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\ResolvedColumn::class, $reference->binding);
        self::assertNotNull($statement->tables[0]->declaration);
        self::assertSame($statement->tables[0]->declaration->columns[0], $reference->binding->column);
    }
}
