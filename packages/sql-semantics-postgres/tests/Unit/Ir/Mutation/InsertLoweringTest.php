<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows;
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
        self::assertSame('val', $value->target->columns[0]->value);
        self::assertSame('id', $value->target->columns[1]->value);
        self::assertNull($value->rows[0]->values[0]->value);
        self::assertSame('-2', $value->rows[0]->values[1]->value?->decimal);
    }
}
