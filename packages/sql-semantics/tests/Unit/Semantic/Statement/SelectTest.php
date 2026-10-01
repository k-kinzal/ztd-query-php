<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Statement;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Projection\Field;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Statement\Select::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SelectTest extends TestCase
{
    public function testWithFieldsPreservesScopeAndTheOriginalStatement(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        $updated = $statement->withFields($statement->fields()->addField(new Field($statement->scope->column(new Name('label')))));
        self::assertSame('SELECT foo FROM bar', $statement->toString());
        self::assertSame('SELECT foo, label FROM bar', $updated->toString());
        self::assertSame($statement->scope, $updated->scope);
    }

    public function testFieldsAndFieldExposeMeaningWithoutTreeNavigation(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite);
        self::assertSame($statement->fields()->items[0], $statement->field('foo'));
        self::assertSame('integer', $statement->field('foo')->type->name);
    }

    public function testToStringWorksForAStatementConstructedWithoutParsing(): void
    {
        $scope = new \SqlSemantics\Semantic\Scope(Dialect::Sqlite);
        $statement = new \SqlSemantics\Semantic\Statement\Select(new \SqlSemantics\Semantic\Projection\Fields($scope, new Field(new \SqlSemantics\Semantic\Expression\Literal(Dialect::Sqlite, 42))));
        self::assertSame('SELECT 42', $statement->toString());
    }
}
