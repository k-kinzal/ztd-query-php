<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Platform\MySql\SemanticRelations::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SemanticRelationsTest extends TestCase
{
    public function testRelationPreservesAnAliasOccurrence(): void
    {
        $statement = \Tests\Scenario\SemanticCases::select(Dialect::MySql, 'SELECT b.foo FROM bar b');
        self::assertSame('bar AS b', $statement->tables[0]->toString());
    }

    public function testJoinedPreservesOuterJoinNullExtension(): void
    {
        $statement = \Tests\Scenario\SemanticCases::select(Dialect::MySql, 'SELECT b.foo FROM bar a LEFT JOIN bar b ON a.foo = b.foo');
        self::assertSame(\SqlSemantics\Core\Type\Nullability::MaybeNull, $statement->field('foo')->expression->nullability);
    }
}
