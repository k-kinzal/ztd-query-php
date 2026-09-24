<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\CreateAllTablesPublicationStatement::class)]
#[Medium]
final class CreateAllTablesPublicationStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR ALL TABLES');
        self::assertInstanceOf(Statement\CreateAllTablesPublicationStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Create, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR ALL TABLES');
        self::assertInstanceOf(Statement\CreateAllTablesPublicationStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR ALL TABLES');
        self::assertInstanceOf(Statement\CreateAllTablesPublicationStatement::class, $statement);
        $changed = $statement->withOptions(new Operand\PublicationOptions([Operand\PublishedOperation::Delete], true));
        self::assertStringEndsWith("(publish = 'delete', publish_via_partition_root = true)", $changed->toString());
        self::assertNotSame($changed->options, $statement->options);
    }
}
