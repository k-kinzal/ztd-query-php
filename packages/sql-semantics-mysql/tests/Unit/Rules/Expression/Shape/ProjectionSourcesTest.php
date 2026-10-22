<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\ProjectionSources;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;

#[CoversClass(ProjectionSources::class)]
#[Small]
final class ProjectionSourcesTest extends TestCase
{
    public function testExpressionsRetainsBothSetContributors(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 7 UNION ALL SELECT 10');
        self::assertInstanceOf(QueryRoot::class, $operation->statement);
        $sources = (new ProjectionSources($operation->facts))->expressions($operation->statement->query, 0, true);
        self::assertCount(2, $sources);
        self::assertEquals([new NumberLiteral('7'), new NumberLiteral('10')], $sources);
    }

    public function testFieldRetainsAStarFieldsResolvedSource(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM (SELECT 7 AS i) q');
        $sources = new ProjectionSources($operation->facts);
        self::assertEquals([new NumberLiteral('7')], $sources->field($operation->field(0), true, []));
        self::assertSame([null], $sources->field($operation->field(0), false, []));
    }

    public function testColumnFollowsACommonTableByItsResolvedIdentity(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 7 AS i) SELECT i FROM c');
        $resolution = $operation->field(0)->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $resolution);
        self::assertEquals([new NumberLiteral('7')], (new ProjectionSources($operation->facts))->column($resolution, []));
    }

    public function testConstantDistinguishesIndependentExpressionsFromAggregates(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $constant = $semantics->analyze('SELECT * FROM (SELECT 7 AS i UNION ALL SELECT 10) q');
        $aggregate = $semantics->analyze('SELECT MAX(7)');
        self::assertInstanceOf(QueryRoot::class, $constant->statement);
        self::assertInstanceOf(QueryRoot::class, $aggregate->statement);
        self::assertTrue((new ProjectionSources($constant->facts))->constant($constant->statement->query));
        self::assertFalse((new ProjectionSources($aggregate->facts))->constant($aggregate->statement->query));
    }
}
