<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\MySql\Lowering\Utility\Set\StoredAssignments::class)]
#[Medium]
final class StoredAssignmentsTest extends TestCase
{
    public function testReadLeavesTransactionFormsWithTheirOwnRules(): void
    {
        self::assertSame('CREATE PROCEDURE p() SET TRANSACTION READ ONLY', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() SET TRANSACTION READ ONLY')->toString());
    }

    public function testContinuedKeepsScopesAndConnectionItems(): void
    {
        self::assertSame('CREATE PROCEDURE p() SET @x = (1, 2), NAMES utf8mb4, SESSION autocommit = ON', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() SET @x=(1,2),NAMES utf8mb4,SESSION autocommit=ON')->toString());
    }
}
