<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect as ParsedInsertQuery;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(InsertLowering::class)]
#[Medium]
final class InsertLoweringTest extends TestCase
{
    public function testLowerResolvesWritableNamesAndLiteralCells(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE public.t (id INTEGER, val INTEGER)');
        $operation = $semantics->inspect('INSERT INTO public.t (val, id) VALUES (NULL, -2)', [$table]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);

        $value = (new InsertLowering())->lower($operation->binding(), $operation->statement);
        self::assertSame('val', $value->target->relation->columns[$value->target->written[0]->slot]->value);
        self::assertSame('id', $value->target->relation->columns[$value->target->written[1]->slot]->value);
        self::assertNull($value->rows[0]->values[0]->value);
        $integer = $value->rows[0]->values[1]->value;
        self::assertInstanceOf(IntegerLiteral::class, $integer);
        self::assertSame('-2', $integer->decimal);
    }

    public function testLowerQueryRetainsTheInputProjection(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE app.events (id INT, amount INT)');
        $operation = $inspection->inspect('INSERT INTO app.events (amount, id) SELECT 7 AS value, 5 AS id', [$declaration]);
        self::assertInstanceOf(ParsedInsertQuery::class, $operation->statement);

        $query = (new InsertLowering())->lowerQuery($operation->binding(), $operation->statement);
        self::assertSame('amount', $query->target->relation->columns[$query->target->written[0]->slot]->value);
        self::assertSame('value', $query->query->columns[0]->name->value);
    }
}
