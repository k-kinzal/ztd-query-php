<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SourceBinding::class)]
#[Small]
final class SourceBindingTest extends TestCase
{
    public function testReferenceAndSourceKeepTheBoundColumnName(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.t (A INTEGER, b INTEGER)');
        $operation = $semantics->inspect('SELECT a FROM ir_fixture.t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $result = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $binding = new SourceBinding($operation->binding(), $select->from);
        self::assertSame($select->from, $binding->relation());
        self::assertSame(0, $binding->reference($result->expression)->slot);
        self::assertSame('A', $binding->source()?->columns[0]->value);
    }

    public function testSourceIsOptional(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->source());
    }

    public function testRelationReturnsTheBoundOccurrence(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');

        self::assertNull((new SourceBinding($operation->binding(), null))->relation());
    }
}
