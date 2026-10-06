<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesExpression;

#[CoversClass(NamesExpression::class)]
#[Medium]
final class NamesExpressionTest extends TestCase
{
    public function testDeriveItemReportsTheRefusal(): void
    {
        $set = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SET NAMES = 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(NamesExpression::class, $set->statement->items[0]);
        self::assertSame('SET NAMES takes a character set name, not an expression', $set->facts->diagnostics[0]->message());
    }

    public function testRenderWritesAnEqualsSign(): void
    {
        self::assertSame('SET @a = 1, NAMES = @b', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set @a = 1, names := @b')->toString());
    }
}
