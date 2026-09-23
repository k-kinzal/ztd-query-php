<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Assignment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Storage\ElementPath;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ScalarAssignment::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ScalarAssignmentTest extends TestCase
{
    public function testArrayElementHasAWritableBaseAndAnUnevaluatedIndex(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, items INTEGER[])');
        $statement = (new Binder($schema))->bind('UPDATE t SET items[id]=7');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(ScalarAssignment::class, $statement->writes[0]);
        $assignment = $statement->writes[0];
        self::assertInstanceOf(ElementPath::class, $assignment->target);
        self::assertSame('items', $assignment->target->column()->columnBinding()?->column->name);
        self::assertSame('id', $assignment->target->index->columnBinding()?->column->name);
        self::assertSame('integer', $assignment->target->type()->name);
        self::assertInstanceOf(Literal::class, $assignment->value);
        self::assertSame('7', $assignment->value->text);
        self::assertCount(1, $assignment->destinations());
        self::assertSame('UPDATE "public"."t" SET "items"["id"] = 7', $statement->toString());
    }
}
