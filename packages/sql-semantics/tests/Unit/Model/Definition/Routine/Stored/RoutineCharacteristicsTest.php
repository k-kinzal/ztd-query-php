<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoutineCharacteristics::class)]
#[Medium]
final class RoutineCharacteristicsTest extends TestCase
{
    public function testDefaultsMatchTheServer(): void
    {
        $characteristics = new RoutineCharacteristics();
        self::assertFalse($characteristics->deterministic);
        self::assertSame(SqlDataAccess::Contains, $characteristics->dataAccess);
        self::assertSame(RoutineSecurity::Definer, $characteristics->security);
        self::assertNull($characteristics->comment);
    }

    public function testRejectsACommentThatIsNotText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $statement);
        $comment = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(Literal::class, $comment);
        $this->expectException(InvalidStructure::class);
        new RoutineCharacteristics(comment: $comment);
    }

    public function testRetainsDeclaredCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE PROCEDURE p() COMMENT 'x' NOT DETERMINISTIC READS SQL DATA SQL SECURITY INVOKER DETERMINISTIC BEGIN END");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertTrue($statement->characteristics->deterministic);
        self::assertSame(SqlDataAccess::Reads, $statement->characteristics->dataAccess);
        self::assertSame(RoutineSecurity::Invoker, $statement->characteristics->security);
        self::assertSame("'x'", $statement->characteristics->comment?->text);
    }
}
