<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Write\StoragePathBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StoragePathBinder::class)]
#[Medium]
final class StoragePathBinderTest extends TestCase
{
    public function testBindNarrowsColumnsAndAccessesRootedInAColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, j INT[], r point)')))->bind('SELECT a, j[1], j[1:2], (r).x FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        [$column, $element, $slice, $field] = array_map(static fn ($output) => StoragePathBinder::bind($output->expression), $statement->outputs);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\ColumnPath::class, $column);
        self::assertSame('a', $column->reference->columnBinding()?->column->name);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\ElementPath::class, $element);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\ColumnPath::class, $element->base);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $element->index);
        self::assertSame('1', $element->index->text);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\SlicePath::class, $slice);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $slice->upper);
        self::assertSame('2', $slice->upper->text);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\FieldPath::class, $field);
        self::assertSame('x', $field->field);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\ColumnPath::class, $field->base);
    }

    public function testBindAcceptsAnUnresolvedColumnAsAPath(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT zz FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $path = StoragePathBinder::bind($statement->outputs[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\ColumnPath::class, $path);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $path->reference);
    }

    public function testBindRejectsAnExpressionThatIsNotAStorageLocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('SELECT 1 FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AssignmentDestination->message());
        StoragePathBinder::bind($statement->outputs[0]->expression);
    }

    public function testBindDiagnosesAWholeRowAssignmentDestination(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AssignmentDestination->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x INT, y INT)')))->bind('UPDATE t SET x.* = DEFAULT, y = DEFAULT');
    }
}
