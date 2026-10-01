<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Relation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Relation\Join::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class JoinTest extends TestCase
{
    public function testToStringPreservesOuterJoinGroupingAcrossAnalysis(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT a.foo FROM bar a LEFT JOIN bar b ON a.foo = b.foo');
        $rebuilt = SemanticCases::select(Dialect::Sqlite, $statement->toString());
        self::assertInstanceOf(\SqlSemantics\Semantic\Relation\Join::class, $rebuilt->scope->sources[0]);
        self::assertSame(\SqlSemantics\Core\Model\JoinKind::Left, $rebuilt->scope->sources[0]->kind);
    }
}
