<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\Operands::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OperandsTest extends TestCase
{
    public function testCheckAcceptsTheScopeOfAPersistentlyAddedField(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        $expression = $statement->scope->column(new \SqlSemantics\Semantic\Name('label'));
        \SqlSemantics\Semantic\Expression\Operands::check($statement->scope, $expression);
        $updated = $statement->withFields($statement->fields()->addField(new \SqlSemantics\Semantic\Projection\Field($expression)));
        self::assertSame($expression, $updated->field('label')->expression);
        self::assertCount(1, $statement->fields()->items);
    }

    public function testFactsUseDialectArithmeticRules(): void
    {
        $statement = SemanticCases::select(\SqlSemantics\Platform\MySql\Dialect::MySql, 'SELECT foo + 1 AS n FROM bar');
        self::assertSame('bigint', $statement->field('n')->type->name);
    }
    public function testFactsRetainKnownComparisonTypesWithAnAbsentCatalog(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo = 1 AS equal FROM bar', false);
        self::assertSame('integer', $statement->field('equal')->type->name);
    }
    public function testProjectedTypeResolvesPostgreSqlNullOutputToText(): void
    {
        $literal = new \SqlSemantics\Semantic\Expression\Literal(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, null);
        self::assertSame('text', \SqlSemantics\Semantic\Expression\Operands::projectedType($literal)->name);
        $statement = SemanticCases::select(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'SELECT NULL AS value');
        self::assertSame('text', $statement->field('value')->type->name);
    }
}
