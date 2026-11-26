<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjectionLowering;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LocalProjectionLowering::class)]
#[Medium]
final class LocalProjectionLoweringTest extends TestCase
{
    public function testLowerResolvesTheOuterColumnByItsBoundOrdinal(): void
    {
        $query = (new Inspection())->inspect('SELECT q.b AS selected_b, q.a AS selected_a FROM (SELECT 1 AS a, 2 AS b) AS q');

        $local = (new LocalProjectionLowering())->lower($query->binding());

        self::assertSame(1, $local->columns[0]->slot);
        self::assertSame('selected_b', $local->columns[0]->name->value);
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
