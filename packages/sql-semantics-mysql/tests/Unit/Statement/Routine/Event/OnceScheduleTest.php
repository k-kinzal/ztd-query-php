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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OnceSchedule::class)]
#[Medium]
final class OnceScheduleTest extends TestCase
{
    public function testDeriveScheduleDerivesTheTime(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE EVENT e ON SCHEDULE AT NOW() DO SELECT 1', []);
        $statement = $create->statement;
        self::assertInstanceOf(CreateEvent::class, $statement);
        self::assertInstanceOf(OnceSchedule::class, $statement->schedule);
        $fact = $create->facts->scalar($statement->schedule->at);
        self::assertInstanceOf(Known::class, $fact->type);

        self::assertInstanceOf(Temporal::class, $fact->type->descriptor);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveScheduleReportsAColumnEvenWhenATableHasIt(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a DATETIME)');
        $create = $semantics->analyze('CREATE EVENT e ON SCHEDULE AT a DO SELECT a FROM t', [$table]);

        self::assertSame(['Column a does not exist.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $create->facts->diagnostics));
    }

    public function testDeriveScheduleResolvesALocalVariableOfTheEnclosingProcedure(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE v DATETIME; ALTER EVENT e ON SCHEDULE AT v; END', []);

        self::assertSame([], $create->facts->diagnostics);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerRenderWritesAtAndTheTime(): iterable
    {
        yield 'MySQL 5.6 text' => ['mysql-5.6.51', "create event e on schedule at '2030-01-01 00:00:00' do select 1", "CREATE EVENT e ON SCHEDULE AT '2030-01-01 00:00:00' DO SELECT 1"];
        yield 'MySQL 8.0 interval arithmetic' => ['mysql-8.0.44', 'alter event e on schedule at current_timestamp + interval 1 hour', 'ALTER EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP + INTERVAL 1 HOUR'];
        yield 'MySQL 9.1 user variable' => ['mysql-9.1.0', 'create event e on schedule at @t do select 1', 'CREATE EVENT e ON SCHEDULE AT @t DO SELECT 1'];
    }

    #[DataProvider('providerRenderWritesAtAndTheTime')]
    public function testRenderWritesAtAndTheTime(string $release, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Semantics(Dialect::MySql, $release))->analyze($sql)->toString());
    }

    public function testRenderWritesAConstructedSchedule(): void
    {
        $out = new Output(new Codec((new Semantics(Dialect::MySql))->profile()->grammar));
        (new OnceSchedule(new NumberLiteral('20300101')))->render($out);

        self::assertSame('AT 20300101', (new Lexical())->join($out->pieces()));
    }
}
