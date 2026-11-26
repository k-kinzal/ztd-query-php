<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ExpressionMethod::class)]
#[Medium]
final class ExpressionMethodTest extends TestCase
{
    public function testDeriveMethodReportsAColumnTheTableDoesNotHave(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');

        self::assertSame('Column q does not exist.', $semantics->diagnose('ALTER TABLE t PARTITION BY RANGE (q) (PARTITION p VALUES LESS THAN MAXVALUE)', [$table])->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheKind(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY LIST (a) (PARTITION p VALUES IN (1, 2))', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY LIST (a) (PARTITION p VALUES IN (1, 2))')->toString());
    }
}
