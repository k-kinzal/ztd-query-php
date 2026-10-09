<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(OperandColumns::class)]
#[Medium]
final class OperandColumnsTest extends TestCase
{
    public function testMessageNamesBothWidths(): void
    {
        self::assertSame('Operand should contain 2 column(s), not 3.', (new OperandColumns(2, 3))->message());
    }

    public function testEqualWidthsAreRejected(): void
    {
        $this->expectExceptionMessage('An operand column problem names two different column counts.');

        new OperandColumns(1, 1);
    }

    public function testMessageKeepsTheOwningPredicateWhenWidthsAreIdentical(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 IN (SELECT 1,2), 1 = ALL (SELECT 1,2)');
        $query = $operation->statement;
        $diagnostics = $operation->facts->diagnostics;

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(SelectExpression::class, $query->items[0]);
        self::assertInstanceOf(SelectExpression::class, $query->items[1]);
        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(OperandColumns::class, $diagnostics[0]);
        self::assertInstanceOf(OperandColumns::class, $diagnostics[1]);
        self::assertSame($query->items[0]->expression, $diagnostics[0]->expression);
        self::assertSame($query->items[1]->expression, $diagnostics[1]->expression);
    }
}
