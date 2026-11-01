<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\IndexOrdering;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\Key as K;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(IndexOrdering::class)]
#[Small]
final class IndexOrderingTest extends TestCase
{
    public function testLegacySeparatesIgnoredDirectionsFromOrdinaryOrdering(): void
    {
        $rule = new IndexOrdering();
        $parts = [new K\ColumnPart(new Name('a'), direction: Direction::Descending)];
        self::assertTrue($rule->legacy(K\IndexKind::FullText, $parts));
        self::assertTrue($rule->legacy(K\IndexKind::Spatial, $parts));
        self::assertTrue($rule->legacy(K\IndexKind::Index, $parts, K\IndexAlgorithm::Hash));
        self::assertFalse($rule->legacy(K\IndexKind::FullText, [new K\ColumnPart(new Name('a'))]));
        self::assertFalse($rule->legacy(K\IndexKind::Index, $parts, K\IndexAlgorithm::Hash, [new K\Option\IndexUsing(K\IndexAlgorithm::Btree)]));
    }
    public function testUnorderedUsesTheFinalExplicitAlgorithm(): void
    {
        self::assertFalse((new IndexOrdering())->unordered(K\IndexKind::Unique, K\IndexAlgorithm::Hash, [new K\Option\IndexUsing(K\IndexAlgorithm::Btree)]));
        self::assertTrue((new IndexOrdering())->unordered(K\IndexKind::Unique, K\IndexAlgorithm::Btree, [new K\Option\IndexUsing(K\IndexAlgorithm::Hash)]));
    }

}
