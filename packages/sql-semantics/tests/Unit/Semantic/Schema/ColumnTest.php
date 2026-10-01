<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Schema\Column::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ColumnTest extends TestCase
{
    public function testDeclarationNullabilitySurvivesOuterJoinAnalysis(): void
    {
        $table = \Tests\Scenario\SemanticCases::table(Dialect::Sqlite);
        $statement = (new \SqlSemantics\Facade\Semantics(Dialect::Sqlite))->analyze('SELECT b.foo FROM bar a LEFT JOIN bar b ON a.foo = b.foo', [$table]);
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\Select::class, $statement);
        self::assertSame(\SqlSemantics\Core\Type\Nullability::NotNull, $table->columns[0]->nullability);
        self::assertSame(\SqlSemantics\Core\Type\Nullability::MaybeNull, $statement->field('foo')->expression->nullability);
    }
}
