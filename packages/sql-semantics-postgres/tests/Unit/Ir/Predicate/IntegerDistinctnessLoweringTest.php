<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctnessLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\DistinctTest;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(IntegerDistinctnessLowering::class)]
#[Small]
final class IntegerDistinctnessLoweringTest extends TestCase
{
    public function testLowerRetainsNullAndTheRequestedEqualityPolarity(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 2 IS NOT DISTINCT FROM NULL');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(DistinctTest::class, $operation->statement->where);

        $test = (new IntegerDistinctnessLowering())->lower(
            $operation->statement->where,
            new SourceBinding($operation->binding(), null),
            true,
        );
        self::assertInstanceOf(IntegerLiteral::class, $test->left);
        self::assertSame('2', $test->left->decimal);
        self::assertInstanceOf(NullLiteral::class, $test->right);
        self::assertTrue($test->negated);
    }
}
