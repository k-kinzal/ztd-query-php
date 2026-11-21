<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;

#[CoversClass(SourceBinding::class)]
#[Small]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE public.t ("A" INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT "A" FROM public.t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $result);

        $binding = new SourceBinding($operation, $select->from);
        self::assertSame(0, $binding->reference($result->expression)->slot);
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testSourceIsOptional(): void
    {
        $operation = (new Semantics())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation, null))->source());
    }
}
