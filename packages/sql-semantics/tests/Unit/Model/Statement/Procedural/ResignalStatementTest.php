<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Procedural\ResignalStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResignalStatement::class)]
#[Medium]
final class ResignalStatementTest extends TestCase
{
    public function testWithOriginRetainsConditionAndItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("RESIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 1644");
        self::assertInstanceOf(ResignalStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("RESIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 1644", $copy->toString());
    }

    public function testWithConditionAddsOrRemovesTheReplacementState(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESIGNAL');
        self::assertInstanceOf(ResignalStatement::class, $statement);
        $changed = $statement->withCondition(new SqlState('HY000'));
        self::assertSame("RESIGNAL SQLSTATE 'HY000'", $changed->toString());
        self::assertSame('RESIGNAL', $changed->withCondition(null)->toString());
        self::assertNull($statement->condition);
    }

    public function testWithAssignmentsReplacesTheItems(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('RESIGNAL');
        $source = $binder->bind("RESIGNAL SET MESSAGE_TEXT = 'x'");
        self::assertInstanceOf(ResignalStatement::class, $statement);
        self::assertInstanceOf(ResignalStatement::class, $source);
        self::assertSame("RESIGNAL SET MESSAGE_TEXT = 'x'", $statement->withAssignments($source->assignments)->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESIGNAL');
        $this->expectException(InvalidStructure::class);
        new ResignalStatement(new Origin('s0', $statement->source, Dialect::Sqlite));
    }
}
