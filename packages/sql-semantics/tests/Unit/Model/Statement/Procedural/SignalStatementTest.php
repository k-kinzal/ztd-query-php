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
use SqlSemantics\Model\Statement\Procedural\SignalStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SignalStatement::class)]
#[Medium]
final class SignalStatementTest extends TestCase
{
    public function testWithOriginRetainsConditionAndItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE VALUE '45000' SET MESSAGE_TEXT = 'stop'");
        self::assertInstanceOf(SignalStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->assignments, $copy->assignments);
        self::assertSame("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stop'", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithConditionSignalsAnotherState(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000'");
        self::assertInstanceOf(SignalStatement::class, $statement);
        $changed = $statement->withCondition(new SqlState('01000'));
        self::assertSame("SIGNAL SQLSTATE '01000'", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('45000', $statement->condition->code);
    }

    public function testWithAssignmentsReplacesTheItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stop'");
        self::assertInstanceOf(SignalStatement::class, $statement);
        self::assertSame("SIGNAL SQLSTATE '45000'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withAssignments([])));
        self::assertCount(1, $statement->assignments);
    }

    public function testRejectsARepeatedItem(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stop'");
        self::assertInstanceOf(SignalStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new SignalStatement($statement->origin, $statement->condition, [$statement->assignments[0], $statement->assignments[0]]);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000'");
        $this->expectException(InvalidStructure::class);
        new SignalStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), new SqlState('45000'));
    }
}
