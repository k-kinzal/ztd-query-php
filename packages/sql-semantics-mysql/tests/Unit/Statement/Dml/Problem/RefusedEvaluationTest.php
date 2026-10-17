<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedEvaluation;

#[CoversClass(RefusedEvaluation::class)]
#[Medium]
final class RefusedEvaluationTest extends TestCase
{
    public function testMessageRetainsAllDependenciesInTheRefusedInstruction(): void
    {
        try {
            (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DO (1,2), (SELECT a FROM missing)');
            self::fail('A row cannot be an ordinary DO scalar.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(RefusedEvaluation::class, $failure->problem);
            self::assertSame('Operand should contain 1 column(s), not 2.', $failure->problem->message());
            self::assertCount(2, $failure->problem->evaluation->items);
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $failure->problem->evaluation->items[1]);
        }
    }
}
