<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexOptionsLowering;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(IndexOptionsLowering::class)]
#[Medium]
final class IndexOptionsLoweringTest extends TestCase
{
    public function testVisibleUsesTheLastVisibilityOption(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.events (id INT NOT NULL)']);
        $index = $semantics->analyze('CREATE INDEX ix ON app.events (id) USING BTREE VISIBLE INVISIBLE', $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertFalse($index->visible);
    }
}
