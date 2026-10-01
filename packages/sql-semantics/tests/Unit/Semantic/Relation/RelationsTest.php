<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Relation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Relation\Relations::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RelationsTest extends TestCase
{
    public function testTablesAndNullableDistinguishAnOuterJoinSide(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT a.foo, b.foo AS bfoo FROM bar a LEFT JOIN bar b ON a.foo = b.foo');
        $source = $statement->scope->sources[0];
        self::assertSame($statement->tables, \SqlSemantics\Semantic\Relation\Relations::tables($source));
        self::assertSame([$statement->tables[1]], \SqlSemantics\Semantic\Relation\Relations::nullable($source));
        self::assertSame(\SqlSemantics\Core\Type\Nullability::MaybeNull, $statement->field('bfoo')->expression->nullability);
    }
    public function testNullableDoesNotExtendAnInnerJoin(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT a.foo FROM bar a JOIN bar b ON a.foo = b.foo');
        self::assertSame([], \SqlSemantics\Semantic\Relation\Relations::nullable($statement->scope->sources[0]));
    }
}
