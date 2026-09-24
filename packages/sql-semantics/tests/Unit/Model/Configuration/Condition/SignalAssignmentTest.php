<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;
use SqlSemantics\Model\Configuration\Condition\SignalAssignment;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Procedural\SignalStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SignalAssignment::class)]
#[Medium]
final class SignalAssignmentTest extends TestCase
{
    public function testWithValueKeepsTheItem(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MYSQL_ERRNO = 1644");
        self::assertInstanceOf(SignalStatement::class, $statement);
        $changed = $statement->assignments[0]->withValue($statement->assignments[1]->value);
        self::assertSame(ConditionItem::MessageText, $changed->item);
        self::assertSame('1644', $changed->value->spelling());
    }

    public function testRejectsTheReturnedSqlState(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a'");
        self::assertInstanceOf(SignalStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new SignalAssignment(ConditionItem::ReturnedSqlState, $statement->assignments[0]->value);
    }

    public function testRejectsNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DO NULL');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Execution\DoExpressionsStatement::class, $statement);
        $null = $statement->expressions[0];
        self::assertInstanceOf(Literal::class, $null);
        $this->expectException(InvalidStructure::class);
        new SignalAssignment(ConditionItem::MessageText, $null);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("SELECT 'a'");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $text = $statement->outputs[0]->expression;
        self::assertInstanceOf(Literal::class, $text);
        $this->expectException(InvalidStructure::class);
        new SignalAssignment(ConditionItem::MessageText, $text);
    }
}
