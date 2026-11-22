<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Recursive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Recursive\RecursiveSeriesLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(RecursiveSeriesLowering::class)]
#[Medium]
final class RecursiveSeriesLoweringTest extends TestCase
{
    public function testLowerPublishesTheSameWorkingSlotAsTheResolvedQuery(): void
    {
        $query = (new Inspection())->inspect('WITH RECURSIVE walk(n) AS (SELECT 1 UNION ALL SELECT walk.n + 2 FROM walk WHERE n < 6) SELECT n AS value FROM walk');
        $root = $query->statement;
        self::assertInstanceOf(QueryExpression::class, $root);
        $series = (new RecursiveSeriesLowering())->lower($query->binding(), $root);
        self::assertSame(['1', '2', '6'], [$series->start->decimal, $series->increment->decimal, $series->boundary->decimal]);
        self::assertSame(0, $series->columns[0]->slot);
    }
}
