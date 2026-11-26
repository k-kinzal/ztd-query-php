<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ColumnsMethod::class)]
#[Medium]
final class ColumnsMethodTest extends TestCase
{
    public function testDeriveMethodReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');

        self::assertSame('Column b does not exist in the table.', $semantics->diagnose('ALTER TABLE t PARTITION BY RANGE COLUMNS (a, b) (PARTITION p VALUES LESS THAN (1, 2))', [$table])->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LIST COLUMNS (a, b)', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY LIST COLUMNS (a, b)')->toString());
    }
}
