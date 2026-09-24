<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Replication\PublicationMembers::class)]
#[Medium]
final class PublicationMembersTest extends TestCase
{
    public function testReadContinuesTheKindOfThePreviousObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t, public.t2, TABLES IN SCHEMA s, "S", CURRENT_SCHEMA', strict: false);
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        self::assertInstanceOf(Operand\PublishedTable::class, $statement->objects[1]);
        self::assertInstanceOf(Operand\PublishedSchema::class, $statement->objects[3]);
        self::assertInstanceOf(Operand\PublishedCurrentSchema::class, $statement->objects[4]);
    }

    #[TestWith(['CREATE PUBLICATION p FOR t'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLES IN SCHEMA s, x (a)'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLES IN SCHEMA s, x WHERE (true)'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLES IN SCHEMA s, x.y'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLE t, CURRENT_SCHEMA'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLE t (a, a)'])]
    public function testReadDiagnosesAnImpossibleList(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PublicationObject->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql);
    }

    #[TestWith(['TABLE ONLY t', true])]
    #[TestWith(['TABLE t, ONLY (t)', true])]
    #[TestWith(['TABLE t *', false])]
    public function testTableRetainsDescendantExclusion(string $objects, bool $only): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR ' . $objects);
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        $table = $statement->objects[count($statement->objects) - 1];
        self::assertInstanceOf(Operand\PublishedTable::class, $table);
        self::assertSame($only, $table->table instanceof \SqlSemantics\Model\Relation\OnlyTableReference);
    }

    public function testSchemaFoldsAnUnquotedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLES IN SCHEMA Sales');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        self::assertEquals([new Operand\PublishedSchema('sales')], $statement->objects);
    }
}
