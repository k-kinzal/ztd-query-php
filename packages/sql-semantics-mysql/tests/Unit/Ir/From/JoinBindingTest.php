<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\From\DerivedBinding;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\From\JoinKind;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
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

    public function testBindKeepsExplicitJoinInsideCommaTree(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE ir_fixture.t (id INTEGER)');
        $operation = $inspection->inspect('SELECT c.id FROM ir_fixture.t a, ir_fixture.t b LEFT JOIN ir_fixture.t c ON b.id=c.id', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $target);
        $binding = JoinBinding::bind($operation->binding(), $operation->statement->from);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $reference = $binding->reference($target->expression);
        $source = $binding->source();

        self::assertSame(2, $reference->occurrence);
        self::assertSame(JoinKind::Cross, $source->kind);
        self::assertInstanceOf(Join::class, $source->right);
        self::assertSame(JoinKind::Left, $source->right->kind);
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

    public function testResolvedFindsTheQualifiedWildcardOccurrence(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (id INTEGER, amount INTEGER)');
        $operation = $inspection->inspect('SELECT b.* FROM app.t AS a CROSS JOIN app.t AS b', [$table]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $binding = $operation->binding();
        $fields = $binding->facts->query($statement)->fields();
        self::assertNotNull($fields);
        $resolution = $fields->at(1)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $source = JoinBinding::bind($binding, $statement->from);
        self::assertInstanceOf(JoinBinding::class, $source);
        $reference = $source->resolved($resolution);

        self::assertSame(1, $reference->occurrence);
        self::assertSame(0, $reference->slot);
    }

    public function testPredicateKeepsAllRowsOfADisjointNaturalOuterJoin(): void
    {
        $mysql = new Inspection();
        $first = $mysql->inspect('CREATE TABLE app.first_input (id INT)');
        $second = $mysql->inspect('CREATE TABLE app.second_input (other INT)');
        $query = $mysql->inspect('SELECT 1 FROM app.first_input NATURAL LEFT JOIN app.second_input', [$first, $second]);
        self::assertInstanceOf(Select::class, $query->statement);
        $binding = JoinBinding::bind($query->binding(), $query->statement->from);
        self::assertInstanceOf(JoinBinding::class, $binding);

        self::assertEquals(new BooleanLiteral(true), $binding->predicate());
    }

    public function testProjectedResolvesTheSecondNativeTextOccurrence(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin)');
        $operation = $inspection->inspect('SELECT b.label FROM app.t a CROSS JOIN app.t b', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $facts = $operation->binding();
        $fields = $facts->facts->query($operation->statement)->fields();
        self::assertNotNull($fields);
        $resolution = $fields->at(0)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        $binding = JoinBinding::bind($facts, $operation->statement->from);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $reference = $binding->projected($resolution);

        self::assertInstanceOf(TextColumnReference::class, $reference);
        self::assertSame(1, $reference->occurrence);
        self::assertSame(0, $reference->slot);
    }

    public function testFieldRetainsTheExactVarcharOccurrence(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin)');
        $query = $inspection->inspect('SELECT b.label FROM app.t a CROSS JOIN app.t b', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $binding = JoinBinding::bind($query->binding(), $query->statement->from);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $field = $binding->field($item->expression);

        self::assertInstanceOf(TextColumnReference::class, $field);
        self::assertSame(1, $field->occurrence);
    }

    public function testMembershipReferenceRetainsTheExactJoinOccurrence(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE ir_fixture.t (n BIGINT)');
        $operation = $inspection->inspect('SELECT right_table.n FROM ir_fixture.t left_table CROSS JOIN ir_fixture.t right_table', [$declaration]);
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $projection = $statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $projection);
        $binding = JoinBinding::bind($operation->binding(), $statement->from);
        self::assertInstanceOf(JoinBinding::class, $binding);

        $field = $binding->membershipReference($projection->expression);
        self::assertInstanceOf(IntegralColumnReference::class, $field);
        self::assertSame(1, $field->occurrence);
        self::assertSame(0, $field->slot);
    }
}
