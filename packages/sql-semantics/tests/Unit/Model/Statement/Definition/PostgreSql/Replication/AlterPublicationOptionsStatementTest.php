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

#[CoversClass(Statement\AlterPublicationOptionsStatement::class)]
#[Medium]
final class AlterPublicationOptionsStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p SET (publish_via_partition_root)');
        self::assertInstanceOf(Statement\AlterPublicationOptionsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Alter, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p SET (publish_via_partition_root)');
        self::assertInstanceOf(Statement\AlterPublicationOptionsStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOptionsRequiresAnOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p SET (publish_via_partition_root)');
        self::assertInstanceOf(Statement\AlterPublicationOptionsStatement::class, $statement);
        self::assertSame('ALTER PUBLICATION "p" SET (publish = \'\')', $statement->withOptions(new Operand\PublicationOptions([]))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withOptions(new Operand\PublicationOptions());
    }
}
