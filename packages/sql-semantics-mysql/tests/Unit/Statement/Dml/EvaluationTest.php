<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;

#[CoversClass(Evaluation::class)]
#[Medium]
final class EvaluationTest extends TestCase
{
    public function testDeriveStatementDerivesTheItemsWithoutOutput(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DO 1 + 1, @a := 2');
        self::assertInstanceOf(Evaluation::class, $operation->statement);

        self::assertCount(2, $operation->statement->items);
        self::assertNull($operation->facts->output);
    }

    public function testDeriveStatementReportsAStarWithoutTables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DO *');

        self::assertSame('No tables used', $operation->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheItems(): void
    {
        self::assertSame('DO 1, 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('do 1, 2')->toString());
        self::assertSame('DO 1 x', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('do 1 x')->toString());
    }

    public function testRenderRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('DO evaluates at least one expression.');

        new Evaluation([]);
    }
}
