<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;

#[CoversClass(ColumnsMethod::class)]
#[Medium]
final class ColumnsMethodTest extends TestCase
{
    public function testDeriveMethodReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertSame('Column b does not exist in the table.', $semantics->analyze('ALTER TABLE t PARTITION BY RANGE COLUMNS (a, b) (PARTITION p VALUES LESS THAN (1, 2))', [$table])->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LIST COLUMNS (a, b)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t PARTITION BY LIST COLUMNS (a, b)')->toString());
    }
}
