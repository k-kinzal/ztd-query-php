<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRangeOperandLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(IntegerRangeOperandLowering::class)]
#[Small]
final class IntegerRangeOperandLoweringTest extends TestCase
{
    public function testLowerRecordsOnlyTheCastAsATypeFixedBeforeComparison(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 2 BETWEEN (NULL) AND CAST((NULL) AS pg_catalog.int4)');
        self::assertInstanceOf(Select::class, $operation->statement);
        $parsed = $operation->statement->where;
        self::assertInstanceOf(Between::class, $parsed);
        $binding = new SourceBinding($operation->binding(), null);
        $operands = new IntegerRangeOperandLowering();

        [$low, $knownLow] = $operands->lower($parsed->low, $binding, true);
        [$high, $knownHigh] = $operands->lower($parsed->high, $binding, true);

        self::assertInstanceOf(NullLiteral::class, $low);
        self::assertInstanceOf(NullLiteral::class, $high);
        self::assertFalse($knownLow);
        self::assertTrue($knownHigh);
    }
}
