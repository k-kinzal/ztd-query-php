<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RelationReaderTest extends TestCase
{
    public function testScopeAllowsAConstantQueryWithNoRelations(): void
    {
        self::assertSame([], SemanticCases::select(Dialect::Sqlite, 'SELECT 1')->scope->tables);
    }

    public function testRelationRetainsSelfJoinOccurrences(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT a.foo FROM bar a, bar b');
        self::assertNotSame($statement->tables[0], $statement->tables[1]);
        self::assertSame($statement->tables[0]->declaration, $statement->tables[1]->declaration);
    }

    public function testTableResolvesTheExactCatalogDeclaration(): void
    {
        $table = SemanticCases::table(Dialect::Sqlite);
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT foo FROM bar', [$table]);
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\Select::class, $statement);
        self::assertSame($table, $statement->tables[0]->declaration);
    }

    public function testKindRejectsUnmodeledNaturalJoinSemantics(): void
    {
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        SemanticCases::select(Dialect::Sqlite, 'SELECT * FROM bar a NATURAL JOIN bar b');
    }

    public function testJoinSeparatesOnEvaluationFromNullExtension(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar a LEFT JOIN bar b ON a.foo = b.foo');
        self::assertSame(\SqlSemantics\Core\Type\Nullability::MaybeNull, $statement->field('foo')->expression->nullability);
        $join = $statement->scope->sources[0];
        self::assertInstanceOf(\SqlSemantics\Semantic\Relation\Join::class, $join);
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\Binary::class, $join->condition);
        self::assertSame(\SqlSemantics\Core\Type\Nullability::NotNull, $join->condition->right->nullability);
    }
}
