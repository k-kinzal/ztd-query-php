<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;

#[CoversClass(ExpressionMethod::class)]
#[Medium]
final class ExpressionMethodTest extends TestCase
{
    public function testDeriveMethodReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertSame('Column q does not exist.', $semantics->analyze('ALTER TABLE t PARTITION BY RANGE (q) (PARTITION p VALUES LESS THAN MAXVALUE)', [$table])->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheKind(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LIST (a) (PARTITION p VALUES IN (1, 2))', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY LIST (a) (PARTITION p VALUES IN (1, 2))')->toString());
    }
}
