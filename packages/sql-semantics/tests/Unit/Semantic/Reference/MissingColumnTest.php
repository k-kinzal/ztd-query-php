<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Reference;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Reference\MissingColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MissingColumnTest extends TestCase
{
    public function testClosedCatalogReportsMissingInsteadOfUnknown(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT absent FROM bar');
        $expression = $statement->field('absent')->expression;
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\ColumnReference::class, $expression);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\MissingColumn::class, $expression->binding);
        self::assertSame('invalid', $expression->type->name);
    }
}
