<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Write\InputRow::class)]
#[Medium]
final class InputRowTest extends TestCase
{
    public function testKeepsTupleDefaultsSeparateFromScalarRowExpressions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER DEFAULT 7,b INTEGER)'));
        $statement = $binder->bind('UPDATE t SET (a,b) = (DEFAULT,1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        $assignment = $statement->writes[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleRowAssignment::class, $assignment);
        self::assertSame(\SqlSemantics\Model\Write\DefaultSource::Column, $assignment->row->items[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $assignment->row->items[1]);
        self::assertSame('1', $assignment->row->items[1]->text);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsDefaultsInSqliteInputRows(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new \SqlSemantics\Model\Write\InputRow(Dialect::Sqlite, [\SqlSemantics\Model\Write\DefaultSource::Column]);
    }

    public function testRejectsAnotherDialectInTheSameRow(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new \SqlSemantics\Model\Write\InputRow(Dialect::PostgreSql, [$query->outputs[0]->expression]);
    }
}
