<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexTableLowering;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(IndexTableLowering::class)]
#[Medium]
final class IndexTableLoweringTest extends TestCase
{
    public function testResolveDistinguishesTheFieldHeadingFromIndexOrder(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.events (id INT NOT NULL, Note INT NULL)']);
        $index = $semantics->analyze('CREATE INDEX ix ON app.events (note DESC, ID)', $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertSame(['id', 'Note'], array_column($index->target->columns, 'value'));
        self::assertSame([1, 0], array_column($index->columns, 'slot'));
        self::assertTrue($index->columns[0]->descending);
    }
}
