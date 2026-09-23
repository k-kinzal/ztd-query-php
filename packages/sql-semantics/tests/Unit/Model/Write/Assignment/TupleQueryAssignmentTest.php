<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Assignment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment\TupleQueryAssignment;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TupleQueryAssignment::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TupleQueryAssignmentTest extends TestCase
{
    public function testQueryOutputsRemainSeparateFromTheUpdateResult(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE t SET (id,n)=(SELECT n,id FROM t) RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(TupleQueryAssignment::class, $statement->writes[0]);
        $assignment = $statement->writes[0];
        self::assertInstanceOf(BoundSelect::class, $assignment->query);
        self::assertSame('n', $assignment->query->outputs[0]->name);
        self::assertCount(2, $assignment->destinations());
        self::assertCount(1, $statement->resultColumns());
        self::assertFalse(property_exists($assignment, 'row'));
        $rebound = (new Binder($schema))->bind($statement->toString());
        self::assertInstanceOf(UpdateTableStatement::class, $rebound);
        self::assertInstanceOf(TupleQueryAssignment::class, $rebound->writes[0]);
    }
}
