<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(RecurringSchedule::class)]
#[Medium]
final class RecurringScheduleTest extends TestCase
{
    public function testDeriveScheduleDerivesTheQuantityAndBothTimes(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze("CREATE EVENT e ON SCHEDULE EVERY 2 HOUR STARTS NOW() ENDS '2030-01-01' DO SELECT 1", []);
        $statement = $create->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);
        $schedule = $statement->schedule;
        self::assertInstanceOf(RecurringSchedule::class, $schedule);
        self::assertNotNull($schedule->starts);
        self::assertNotNull($schedule->ends);
        $quantity = $create->facts->scalar($schedule->quantity)->type;
        $starts = $create->facts->scalar($schedule->starts)->type;
        self::assertInstanceOf(Known::class, $quantity);
        self::assertInstanceOf(Known::class, $starts);

        self::assertInstanceOf(Integral::class, $quantity->descriptor);
        self::assertInstanceOf(Temporal::class, $starts->descriptor);
        self::assertTrue($create->facts->covers($schedule->ends));
        self::assertSame(IntervalUnit::Hour, $schedule->unit);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveScheduleReportsAColumnInEveryExpressionEvenWhenATableHasIt(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE EVERY b MINUTE STARTS a ENDS zz DO SELECT a FROM t', [$table]);

        self::assertSame(['Column b does not exist.', 'Column a does not exist.', 'Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveScheduleDerivesAnEndWithoutAStart(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE EVENT e ON SCHEDULE EVERY 1 MINUTE ENDS zz DO SELECT 1', []);
        $statement = $create->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);
        self::assertInstanceOf(RecurringSchedule::class, $statement->schedule);

        self::assertNull($statement->schedule->starts);
        self::assertSame(['Column zz does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveScheduleResolvesALocalVariableOfTheEnclosingProcedure(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE n INT DEFAULT 1; ALTER EVENT e ON SCHEDULE EVERY n DAY; END', []);

        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesTheIntervalAndTheTimes(): iterable
    {
        yield 'MySQL 5.6 compound unit' => ['mysql-5.6.51', "create event e on schedule every '1:30' hour_minute do select 1", "CREATE EVENT e ON SCHEDULE EVERY '1:30' HOUR_MINUTE DO SELECT 1"];
        yield 'MySQL 5.7 start only' => ['mysql-5.7.44', 'alter event e on schedule every 1 day starts current_timestamp', 'ALTER EVENT e ON SCHEDULE EVERY 1 DAY STARTS CURRENT_TIMESTAMP'];
        yield 'MySQL 8.0 both times' => [
            'mysql-8.0.44',
            'create event e on schedule every 3 week starts now() ends now() + interval 1 year do select 1',
            'CREATE EVENT e ON SCHEDULE EVERY 3 WEEK STARTS NOW() ENDS NOW() + INTERVAL 1 YEAR DO SELECT 1',
        ];
        yield 'MySQL 9.1 end only' => ['mysql-9.1.0', "create event e on schedule every 1 second ends '2031-01-01' do select 1", "CREATE EVENT e ON SCHEDULE EVERY 1 SECOND ENDS '2031-01-01' DO SELECT 1"];
    }

    #[DataProvider('providerRenderWritesTheIntervalAndTheTimes')]
    public function testRenderWritesTheIntervalAndTheTimes(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedSchedule(): void
    {
        $codec = new Codec((new Semantics(Dialect::MySql))->profile()->grammar);
        $bare = new Output($codec);
        (new RecurringSchedule(new NumberLiteral('2'), IntervalUnit::DayHour))->render($bare);
        $bounded = new Output($codec);
        (new RecurringSchedule(new NumberLiteral('2'), IntervalUnit::Hour, new NumberLiteral('1'), new NumberLiteral('3')))->render($bounded);

        self::assertSame('EVERY 2 DAY_HOUR', (new Lexical())->join($bare->pieces()));
        self::assertSame('EVERY 2 HOUR STARTS 1 ENDS 3', (new Lexical())->join($bounded->pieces()));
    }
}
