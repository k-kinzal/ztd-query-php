<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(JoinBinding::class)]
#[Medium]
final class JoinBindingTest extends TestCase
{
    public function testBindSourceKindAndReferenceKeepSelfJoinOccurrencesSeparate(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.t (id INTEGER)');
        $operation = $semantics->inspect('SELECT b.id FROM ir_fixture.t AS a LEFT JOIN ir_fixture.t AS b ON a.id = b.id', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $column = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $column);

        $binding = JoinBinding::bind($operation->binding(), $select->from);
        self::assertInstanceOf(JoinBinding::class, $binding);
        self::assertSame(JoinKind::Left, $binding->kind());
        self::assertSame(1, $binding->reference($column->expression)->occurrence);
        self::assertSame(0, $binding->reference($column->expression)->slot);
        self::assertCount(2, $binding->source()->inputs());
    }
}
