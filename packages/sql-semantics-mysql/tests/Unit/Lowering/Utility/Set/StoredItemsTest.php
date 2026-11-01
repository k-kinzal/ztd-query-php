<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\MySql\Lowering\Utility\Set\StoredItems::class)]
#[Medium]
final class StoredItemsTest extends TestCase
{
    public function testReadPreservesTheValueRoleOfEachTarget(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        self::assertSame('CREATE PROCEDURE p() SET SESSION sql_mode = (1, 2)', $semantics->analyze('CREATE PROCEDURE p() SET SESSION sql_mode=(1,2)')->toString());
        self::assertSame('CREATE PROCEDURE p() SET @@SESSION.sql_mode = (1, 2)', $semantics->analyze('CREATE PROCEDURE p() SET @@SESSION.sql_mode=(1,2)')->toString());
        self::assertSame('CREATE PROCEDURE p() SET @x = 1, @y = (2, 3)', $semantics->analyze('CREATE PROCEDURE p() SET @x=1,@y=(2,3)')->toString());
    }
}
