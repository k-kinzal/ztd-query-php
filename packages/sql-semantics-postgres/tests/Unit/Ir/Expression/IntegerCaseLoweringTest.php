<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseLowering;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(IntegerCaseLowering::class)]
#[Small]
final class IntegerCaseLoweringTest extends TestCase
{
    public function testLowerPreservesTheOrderOfOverlappingArms(): void
    {
        $operation = (new Inspection())->inspect('SELECT CASE WHEN TRUE THEN 2 WHEN TRUE THEN 7 END');
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(CaseExpression::class, $target->expression);
        $binding = new SourceBinding($operation->binding(), null);

        $choice = (new IntegerCaseLowering())->lower($target->expression, $binding, true);
        $first = $choice->arms[0]->result;
        $second = $choice->arms[1]->result;

        self::assertInstanceOf(IntegerLiteral::class, $first);
        self::assertInstanceOf(IntegerLiteral::class, $second);
        self::assertSame('2', $first->decimal);
        self::assertSame('7', $second->decimal);
        self::assertNull($choice->otherwise);
    }

    public function testResultRecognizesTheExplicitInt4Null(): void
    {
        $operation = (new Inspection())->inspect('SELECT CAST((NULL) AS pg_catalog.int4)');
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        $binding = new SourceBinding($operation->binding(), null);

        self::assertInstanceOf(NullLiteral::class, (new IntegerCaseLowering())->result($target->expression, $binding, true));
    }
}
