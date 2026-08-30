<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\FakeConnectionProperties;
use ZtdQuery\Adapter\Mysqli\Driver\ConnectionState;

#[CoversClass(ConnectionState::class)]
final class ConnectionStateTest extends TestCase
{
    public function testErrorNumberAnswersTheNumberTheDriverGaveTheLastFailure(): void
    {
        $state = new ConnectionState(new FakeConnectionProperties(['errno' => 1146]));

        self::assertSame(1146, $state->errorNumber());
    }

    public function testErrorNumberAnswersZeroWhereTheConnectionSaysNothing(): void
    {
        self::assertSame(0, (new ConnectionState(new FakeConnectionProperties()))->errorNumber());
    }

    public function testErrorMessageAnswersWhatTheDriverSaidAboutTheLastFailure(): void
    {
        $state = new ConnectionState(new FakeConnectionProperties(['error' => "Table 'x' doesn't exist"]));

        self::assertSame("Table 'x' doesn't exist", $state->errorMessage());
    }

    public function testErrorMessageAnswersNothingWhereTheConnectionSaysNothing(): void
    {
        self::assertSame('', (new ConnectionState(new FakeConnectionProperties()))->errorMessage());
    }

    public function testAffectedRowsAnswersHowManyRowsTheStatementTouched(): void
    {
        $state = new ConnectionState(new FakeConnectionProperties(['affected_rows' => 3]));

        self::assertSame(3, $state->affectedRows());
    }

    public function testAffectedRowsReadsACountTheDriverWroteAsText(): void
    {
        $state = new ConnectionState(new FakeConnectionProperties(['affected_rows' => '9223372036854775807']));

        self::assertSame(9223372036854775807, $state->affectedRows());
    }

    public function testAffectedRowsAnswersZeroWhereTheConnectionSaysNothing(): void
    {
        self::assertSame(0, (new ConnectionState(new FakeConnectionProperties()))->affectedRows());
    }
    public function testAffectedRowsAnswersZeroWhereTheConnectionSaysSomethingThatIsNoCount(): void
    {
        $state = new ConnectionState(new FakeConnectionProperties(['affected_rows' => 1.9]));

        self::assertSame(0, $state->affectedRows());
    }
}
