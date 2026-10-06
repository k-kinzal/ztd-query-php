<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;

#[CoversClass(HashMethod::class)]
#[Medium]
final class HashMethodTest extends TestCase
{
    public function testDeriveMethodResolvesTheExpressionInTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $alter = $semantics->analyze('ALTER TABLE t PARTITION BY HASH (a)', [$table]);

        self::assertSame([], $alter->facts->diagnostics);
    }

    public function testRenderWritesTheExpression(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LINEAR HASH (a + 1)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY LINEAR HASH (a+1)')->toString());
    }
}
