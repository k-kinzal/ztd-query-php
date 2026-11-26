<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjectionLowering;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(LocalProjectionLowering::class)]
#[Medium]
final class LocalProjectionLoweringTest extends TestCase
{
    public function testLowerResolvesTheOuterColumnByItsBoundOrdinal(): void
    {
        $query = (new Inspection())->inspect('SELECT q.b AS first_value, q.a AS second_value FROM (SELECT 1 AS a, 2 AS b) AS q');

        $local = (new LocalProjectionLowering())->lower($query->binding());

        self::assertSame(1, $local->columns[0]->slot);
        self::assertSame('first_value', $local->columns[0]->name->value);
        self::assertSame(0, $local->columns[1]->slot);
    }

    public function testLowerKeepsTheImmediateDefinitionOfEachSlot(): void
    {
        $query = (new Inspection())->inspect(
            'SELECT oq.x FROM (SELECT iq.b AS x FROM (SELECT 1 AS a, 2 AS b) AS iq) AS oq'
        );
        $local = (new LocalProjectionLowering())->lower($query->binding());

        self::assertInstanceOf(LocalProjection::class, $local->definition);
        self::assertSame([0, 1], [$local->columns[0]->slot, $local->definition->columns[0]->slot]);
    }
}
