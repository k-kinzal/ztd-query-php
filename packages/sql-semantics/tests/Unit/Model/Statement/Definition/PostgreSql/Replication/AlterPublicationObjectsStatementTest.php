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

#[CoversClass(Statement\AlterPublicationObjectsStatement::class)]
#[Medium]
final class AlterPublicationObjectsStatementTest extends TestCase
{
    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p ADD TABLE t WHERE (a > 0)');
        self::assertInstanceOf(Statement\AlterPublicationObjectsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Alter, $copy->kind);
    }

    public function testWithNameRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p ADD TABLE t WHERE (a > 0)');
        self::assertInstanceOf(Statement\AlterPublicationObjectsStatement::class, $statement);
        self::assertSame('q', $statement->withName('q')->name);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithChangeRejectsDroppingAFilteredTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p ADD TABLE t WHERE (a > 0)');
        self::assertInstanceOf(Statement\AlterPublicationObjectsStatement::class, $statement);
        self::assertSame(Operand\PublicationObjectChange::Set, $statement->withChange(Operand\PublicationObjectChange::Set)->change);
        $this->expectException(InvalidStructure::class);
        $statement->withChange(Operand\PublicationObjectChange::Drop);
    }

    public function testWithObjectsRequiresAnObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p ADD TABLE t');
        self::assertInstanceOf(Statement\AlterPublicationObjectsStatement::class, $statement);
        self::assertCount(2, $statement->withObjects([new Operand\PublishedSchema('s'), new Operand\PublishedCurrentSchema()])->objects);
        $this->expectException(InvalidStructure::class);
        $statement->withObjects([]);
    }
}
