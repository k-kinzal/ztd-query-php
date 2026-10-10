<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Operator\DateShift;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\Shift;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Window\Frames;
use MySqlMemory\Plan\Window\Specification;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(Frames::class)]
#[Small]
final class FramesTest extends TestCase
{
    public function testCheckRefusesAnIntervalOfARowsFrameFirst(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3596);
        $this->expectExceptionMessage("Window '<unnamed window>': INTERVAL can only be used with RANGE frames.");

        $session->query('SELECT SUM(1) OVER (ORDER BY 2 + 0 ROWS BETWEEN 1.5 PRECEDING AND INTERVAL 1 DAY FOLLOWING)');
    }

    public function testCheckRefusesAFrameThatStartsAfterItsEnd(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3586);
        $this->expectExceptionMessage("Window 'w': frame start or end is negative, NULL or of non-integral type");

        $session->query('SELECT 1 WINDOW w AS (ROWS BETWEEN 1 FOLLOWING AND CURRENT ROW)');
    }

    public function testRangeRefusesAnOrderingThatIsNotANumberOrATime(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3587);
        $this->expectExceptionMessage("Window '<unnamed window>' with RANGE N PRECEDING/FOLLOWING frame requires exactly one ORDER BY expression, of numeric or temporal type");

        $session->query("SELECT SUM(1) OVER (ORDER BY 'x' RANGE BETWEEN CURRENT ROW AND 1 PRECEDING)");
    }

    public function testRangeRefusesANumberOffsetOfATemporalOrdering(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3588);
        $this->expectExceptionMessage("Window '<unnamed window>' with RANGE frame has ORDER BY expression of datetime type. Only INTERVAL bound value allowed.");

        $session->query("SELECT SUM(1) OVER (ORDER BY DATE '2020-01-01' RANGE 1 PRECEDING)");
    }

    public function testRangeRefusesAnIntervalOffsetOfANumericOrdering(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3589);
        $this->expectExceptionMessage("Window '<unnamed window>' with RANGE frame has ORDER BY expression of numeric type, INTERVAL bound value not allowed.");

        $session->query('SELECT SUM(1) OVER (ORDER BY 2 + 0 RANGE BETWEEN INTERVAL -1 DAY PRECEDING AND CURRENT ROW)');
    }

    public function testRangeRefusesAnOffsetThatIsNotConstant(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, d DATE)');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3590);
        $this->expectExceptionMessage("Window '<unnamed window>' has a non-constant frame bound.");

        $session->query('SELECT SUM(a) OVER (ORDER BY d RANGE BETWEEN INTERVAL a DAY FOLLOWING AND CURRENT ROW) FROM t');
    }

    public function testOffsetRefusesAFractionOfARowsFrame(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3586);

        $session->query('SELECT SUM(1) OVER (ROWS 1.5 PRECEDING)');
    }

    public function testValueEvaluatesAnOffsetWithItsDomain(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);

        self::assertSame(['2', Kind::Integer], [(string) (new Frames($planner))->value(new FrameBound(FrameBoundKind::Preceding, new NumberLiteral('2')), new Scope())[0], (new Frames($planner))->value(new FrameBound(FrameBoundKind::Preceding, new NumberLiteral('2')), new Scope())[1]->kind]);
    }

    public function testQuietKeepsTheWarningsApart(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);

        self::assertNotSame($session->diagnostics, (new Frames($planner))->quiet()->diagnostics);
    }

    public function testCompileDefaultsToTheRowsUpToTheLastPeer(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $frame = (new Frames($planner))->compile(new Specification('w', new WindowSpec(), [], []), [[new ColumnRead(Domain::integer(), 0), false]], new Scope());

        self::assertSame([FrameUnit::Range, FrameBoundKind::CurrentRow], [$frame->unit, $frame->end->kind]);
    }

    public function testBoundMovesTheOrderingValueTowardTheRowsBefore(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $frames = new Frames($planner);
        $numeric = $frames->bound(FrameUnit::Range, new FrameBound(FrameBoundKind::Preceding, new NumberLiteral('1')), [[new ColumnRead(Domain::integer(), 0), true]], new Scope());
        $temporal = $frames->bound(FrameUnit::Range, new FrameBound(FrameBoundKind::Preceding, new NumberLiteral('1'), IntervalUnit::Day), [[new ColumnRead(new Domain(Kind::Date, Field::Date, 10), 0), false]], new Scope());
        $rows = $frames->bound(FrameUnit::Rows, new FrameBound(FrameBoundKind::Following, new NumberLiteral('18446744073709551615')), [], new Scope());

        self::assertInstanceOf(Shift::class, $numeric->limit);
        self::assertFalse($numeric->limit->subtract);
        self::assertInstanceOf(DateShift::class, $temporal->limit);
        self::assertTrue($temporal->limit->subtract);
        self::assertSame(PHP_INT_MAX, $rows->rows);
    }

    public function testOffsetRefusesAParameterOfARowsFrameBoundToAFraction(): void
    {
        $session = (new Instance())->connect();
        $session->query('PREPARE s FROM \'SELECT SUM(1) OVER (ROWS ? PRECEDING)\'');
        $session->query('SET @n = 1.5');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1210);
        $this->expectExceptionMessage('Incorrect arguments to EXECUTE');

        $session->query('EXECUTE s USING @n');
    }
}
