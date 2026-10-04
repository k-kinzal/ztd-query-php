<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;

#[CoversClass(PasswordAssignment::class)]
#[Medium]
final class PasswordAssignmentTest extends TestCase
{
    public function testDeriveItemDerivesNothing(): void
    {
        $set = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("SET @a = 1, PASSWORD = PASSWORD('x')");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(PasswordAssignment::class, $set->statement->items[1]);
        self::assertNull($set->statement->items[1]->user);
        self::assertSame([], $set->facts->diagnostics);
    }

    public function testRenderWritesTheAccount(): void
    {
        self::assertSame("SET PASSWORD FOR u@h = 'x', @a = 1", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set password for u@h = 'x', @a = 1")->toString());
    }
}
