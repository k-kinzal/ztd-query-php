<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\DerivedBinding;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(JoinBinding::class)]
#[Medium]
final class JoinBindingTest extends TestCase
{
    public function testReferenceKeepsSelfJoinOccurrencesSeparate(): void
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
        self::assertSame(1, $binding->reference($column->expression)->occurrence);
        self::assertSame(0, $binding->reference($column->expression)->slot);
    }

    public function testBindKeepsAnAbsentSource(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');
        $binding = JoinBinding::bind($operation->binding(), null);

        self::assertInstanceOf(SourceBinding::class, $binding);
        self::assertNull($binding->source());
    }

    public function testSourceClosesBothOperandsAndTheOperator(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE ir_fixture.t (id INTEGER)');
        $operation = $semantics->inspect('SELECT 1 FROM ir_fixture.t AS a LEFT JOIN ir_fixture.t AS b ON a.id = b.id', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $binding = JoinBinding::bind($operation->binding(), $operation->statement->from);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $source = $binding->source();
        self::assertSame(JoinKind::Left, $source->kind);
        self::assertCount(2, $source->inputs());
    }

    public function testBindChildRecognizesAResolvedDerivedInput(): void
    {
        $operation = (new Inspection())->inspect('SELECT q.x FROM (SELECT 3 AS x) q');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(DerivedTable::class, $operation->statement->from);
        self::assertInstanceOf(DerivedBinding::class, JoinBinding::bindChild($operation->binding(), $operation->statement->from));
    }

    public function testUngroupPreservesAnAbsentSource(): void
    {
        self::assertNull(JoinBinding::ungroup(null));
    }
}
