<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateBinding;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall as ParsedAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(AggregateBinding::class)]
#[Medium]
final class AggregateBindingTest extends TestCase
{
    public function testLowerMapsTheSignedBuiltinOverload(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (id INT)');
        $operation = $inspection->inspect('SELECT sum(DISTINCT id) AS total FROM app.events', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(ParsedAggregate::class, $target->expression);
        $source = new SourceBinding($operation->binding(), $operation->statement->from);
        $value = (new AggregateBinding($operation->binding()->context))->lower($target->expression, $source);

        self::assertSame(AggregateOperator::Sum, $value->operator);
        self::assertSame(0, $value->argument?->slot);
        self::assertTrue($value->distinct);
    }
}
