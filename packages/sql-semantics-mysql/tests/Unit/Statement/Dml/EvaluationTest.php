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
        self::assertInstanceOf(Evaluation::class, $operation);

        self::assertCount(2, $operation->items);
        self::assertNull($operation->output());
    }

    public function testRenderWritesTheItems(): void
    {
        self::assertSame('DO 1, 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('do 1, 2')->toString());
        self::assertSame('DO 1 x', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('do 1 x')->toString());
    }

    public function testRenderRejectsAnEmptyList(): void
    {
        $this->expectExceptionMessage('DO evaluates one or more expressions, not table wildcards.');

        new Evaluation([]);
    }
}
