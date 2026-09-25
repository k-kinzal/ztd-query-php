<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Assignment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment\TupleRowAssignment;
use SqlSemantics\Model\Write\Storage\ColumnPath;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TupleRowAssignment::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TupleRowAssignmentTest extends TestCase
{
    public function testDestinationsRemainOrderedAndTheInputIsARow(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE t SET (n,id)=ROW(2,1)');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(TupleRowAssignment::class, $statement->writes[0]);
        $assignment = $statement->writes[0];
        self::assertCount(2, $assignment->destinations());
        self::assertInstanceOf(ColumnPath::class, $assignment->targets[0]);
        self::assertSame('n', $assignment->targets[0]->column()->columnBinding()?->column->name);
        self::assertSame('id', $assignment->targets[1]->column()->columnBinding()?->column->name);
        self::assertInstanceOf(Literal::class, $assignment->row->items[0]);
        self::assertSame('2', $assignment->row->items[0]->text);
        self::assertFalse(property_exists($assignment, 'query'));
    }

    public function testWithAssignmentsRebindsTheNewRowWithoutChangingTheOriginal(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
        $binder = new Binder($schema);
        $original = $binder->bind('UPDATE t SET (id,n)=ROW(1,2)');
        self::assertInstanceOf(UpdateTableStatement::class, $original);
        $replacement = $binder->bind('UPDATE t SET (id,n)=ROW(n,id)');
        self::assertInstanceOf(UpdateTableStatement::class, $replacement);


        $changed = $original->withAssignments($replacement->writes);
        self::assertSame('UPDATE "public"."t" SET ("id", "n") = ROW(1, 2)', (new \SqlSemantics\SimpleSerializer())->serialize($original));
        self::assertSame('UPDATE "public"."t" SET ("id", "n") = ROW("n", "id")', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }
}
