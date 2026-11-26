<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTestLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTest;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(TruthTestLowering::class)]
#[Small]
final class TruthTestLoweringTest extends TestCase
{
    public function testLowerInterpretsAnExplicitBooleanNullInTheTruthPosition(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE CAST((NULL) AS pg_catalog.bool) IS NOT FALSE');
        self::assertInstanceOf(Select::class, $operation->statement);
        $test = $operation->statement->where;
        self::assertInstanceOf(BooleanTest::class, $test);

        $predicate = (new TruthTestLowering())->lower($test, new SourceBinding($operation->binding(), null), true);
        self::assertSame(TruthOperator::IsNotFalse, $predicate->operator);
        self::assertInstanceOf(NullLiteral::class, $predicate->operand);
    }
}
