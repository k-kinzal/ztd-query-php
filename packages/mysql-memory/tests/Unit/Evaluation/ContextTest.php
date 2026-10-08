<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Context::class)]
#[Small]
final class ContextTest extends TestCase
{
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
}
