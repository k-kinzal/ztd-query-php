<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Context::class)]
#[Small]
final class ContextTest extends TestCase
{
    public function testDeferPropagatesErrorsOutsideAContinuationScope(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1365);

        $context->defer(DataError::DivisionByZero->error());
    }

    public function testDeferPreservesTheFirstErrorAndOrderedConditions(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $context->continueAfterError = true;
        $context->defer(DataError::DivisionByZero->error());
        $context->warn(DataError::TruncatedWrongValue, 'INTEGER', 'x');
        $context->defer(\MySqlMemory\Error\Family\StatementError::WrongArguments->error('ESCAPE'));

        self::assertSame(1365, $context->deferredError?->getCode());
        self::assertTrue($context->deferredError->recorded);
        self::assertSame([['Error', 1365, 'Division by 0'], ['Warning', 1292, "Truncated incorrect INTEGER value: 'x'"], ['Error', 1210, 'Incorrect arguments to ESCAPE']], $session->diagnostics->conditions);
    }

    public function testTimeZoneWithAnEmptyNamedCatalogPreservesOffsetsAndSystemTime(): void
    {
        $session = (new Instance(timeZones: []))->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        self::assertNull($context->timeZone('UTC'));
        self::assertNull($context->timeZone('Asia/Tokyo'));
        self::assertSame('+09:00', $context->timeZone('+09:00')?->name);
        self::assertSame('SYSTEM', $context->timeZone('SYSTEM')?->name);
        $result = $session->query("SELECT CONVERT_TZ('2024-01-01 00:00:00','UTC','+00:00')")[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([[null]], $result->rows);
        $this->expectExceptionCode(1298);
        $session->query("SET time_zone='UTC'");
    }

    public function testTableHandleReservesDistinctOccurrencesAndReusesReplannedOnes(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $first = new stdClass();
        $second = new stdClass();

        self::assertSame(0, $context->tableHandle($table, $first));
        self::assertSame(1, $context->tableHandle($table, $second));
        self::assertSame(0, $context->tableHandle($table, $first));
    }

    public function testWarnRecordsAWarningInAStatementThatIsNotAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $context->warn(DataError::TruncatedWrongValue, 'INTEGER', '12abc');

        self::assertSame([['Warning', 1292, "Truncated incorrect INTEGER value: '12abc'"]], $session->diagnostics->conditions);
    }

    public function testWarnRaisesTheWarningAsAnErrorInAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Truncated incorrect INTEGER value: '12abc'");

        $context->warn(DataError::TruncatedWrongValue, 'INTEGER', '12abc');
    }

    public function testWarningRecordsAWarningInAStatementThatIsNotAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $context->warning(DataError::TruncatedWrongValue, 'DOUBLE', '1.5x');

        self::assertSame([['Warning', 1292, "Truncated incorrect DOUBLE value: '1.5x'"]], $session->diagnostics->conditions);
    }

    public function testWarningRaisesTheWarningAsAnErrorInAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Truncated incorrect DOUBLE value: '1.5x'");

        $context->warning(DataError::TruncatedWrongValue, 'DOUBLE', '1.5x');
    }

    public function testNoteRecordsANoteEvenInAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);

        $context->note(DataError::TruncatedWrongValue, 'INTEGER', '7 ');

        self::assertSame([['Note', 1292, "Truncated incorrect INTEGER value: '7 '"]], $session->diagnostics->conditions);
    }

    public function testWarnMessageRecordsAMessageOfItsOwn(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        $context->warnMessage(DataError::TruncatedWrongValue, "Incorrect datetime value: 'xx'");

        self::assertSame([['Warning', 1292, "Incorrect datetime value: 'xx'"]], $session->diagnostics->conditions);
    }

    public function testWarnMessageRaisesTheMessageAsAnErrorInAStrictWrite(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1292);
        $this->expectExceptionMessage("Incorrect datetime value: 'xx'");

        $context->warnMessage(DataError::TruncatedWrongValue, "Incorrect datetime value: 'xx'");
    }

    public function testWarningLeavesTheKeptValuesAlone(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $context->warning(DataError::DivisionByZero);

        self::assertCount(0, $context->kept);
    }

    public function testZoneReadsTheTimeZoneOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET time_zone = 'europe/paris'");
        $context = new Context(new SqlModes([]), new Diagnostics(), $session->variables, 0.0);

        self::assertSame('Europe/Paris', $context->zone()->name);
    }

    public function testLocalAnswersTheInstantInTheZoneOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET time_zone = '+09:00'");
        $context = new Context(new SqlModes([]), new Diagnostics(), $session->variables, 1700000000.25);

        self::assertSame([2023, 11, 15, 7, 13, 20, 250000], $context->local());
    }
}
