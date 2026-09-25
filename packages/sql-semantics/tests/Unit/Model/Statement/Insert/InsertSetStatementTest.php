<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Statement\Insert\InsertSetStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\InsertMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertSetStatement::class)]
#[Medium]
final class InsertSetStatementTest extends TestCase
{
    public function testBindsOrderedAssignmentsToTheDestination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('INSERT INTO t SET id=1, n=2');
        self::assertInstanceOf(InsertSetStatement::class, $statement);
        self::assertSame('t', $statement->insertion->target->declaration->name);
        self::assertSame(InsertMode::Insert, $statement->mode);
        self::assertSame(StatementKind::Insert, $statement->kind);
        self::assertContainsOnlyInstancesOf(ScalarAssignment::class, $statement->writes);
        self::assertSame(['id', 'n'], array_map(static fn ($write): ?string => $write->destinations()[0]->column()->columnBinding()?->column->name, $statement->writes));
        self::assertSame('INSERT INTO `t` SET `id` = 1, `n` = 2', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testReplaceModeChangesTheOperationIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('REPLACE INTO t SET id=1');
        self::assertInstanceOf(InsertSetStatement::class, $statement);
        self::assertSame(InsertMode::Replace, $statement->mode);
        self::assertSame(StatementKind::Replace, $statement->kind);
        self::assertSame('REPLACE INTO `t` SET `id` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesTheWrites(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('INSERT IGNORE INTO t SET id=1');
        self::assertInstanceOf(InsertSetStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::MySql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->writes, $copy->writes);
        self::assertSame($statement->policy, $copy->policy);
        self::assertSame('INSERT IGNORE INTO `t` SET `id` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithReturningRejectsAMySqlProjection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('INSERT INTO t SET id=1');
        self::assertInstanceOf(InsertSetStatement::class, $statement);
        self::assertSame([], $statement->withReturning([])->outputs);
        $this->expectException(InvalidStructure::class);
        $statement->withReturning([new OutputColumn(0, 'id', Expression::reference(['id'], Dialect::MySql))]);
    }

    public function testRequiresAtLeastOneWrite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('INSERT INTO t SET id=1');
        self::assertInstanceOf(InsertSetStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new InsertSetStatement($statement->origin, $statement->insertion, []);
    }
}
