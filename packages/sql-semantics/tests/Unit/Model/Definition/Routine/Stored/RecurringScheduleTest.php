<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Stored;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Stored\RecurringSchedule;
use SqlSemantics\Model\Scalar\Temporal\MySqlUnit;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RecurringSchedule::class)]
#[Medium]
final class RecurringScheduleTest extends TestCase
{
    public function testRetainsTheIntervalAndWindow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE EVENT e ON SCHEDULE EVERY 90 MINUTE STARTS '2030-01-01' ENDS '2031-01-01' DO DO 1");
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(RecurringSchedule::class, $statement->schedule);
        self::assertSame(MySqlUnit::Minute, $statement->schedule->unit);
        self::assertSame("'2030-01-01'", $statement->schedule->starts?->spelling());
        self::assertSame("CREATE EVENT `e` ON SCHEDULE EVERY 90 MINUTE STARTS '2030-01-01' ENDS '2031-01-01' DO DO 1", $statement->toString());
    }

    public function testRejectsMicrosecondUnits(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(RecurringSchedule::class, $statement->schedule);
        $this->expectException(InvalidStructure::class);
        new RecurringSchedule($statement->schedule->every, MySqlUnit::SecondMicrosecond);
    }

    public function testDiagnosesAMicrosecondInterval(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 5 MICROSECOND DO DO 1', strict: false);
    }
}
