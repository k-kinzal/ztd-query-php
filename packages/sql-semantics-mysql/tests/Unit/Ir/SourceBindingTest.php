<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(SourceBinding::class)]
#[Small]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM ir_fixture.t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

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
