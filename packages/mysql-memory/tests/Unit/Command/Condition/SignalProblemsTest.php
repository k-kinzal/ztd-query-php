<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Condition;

use MySqlMemory\Command\Condition\SignalProblems;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;

#[CoversClass(SignalProblems::class)]
#[Small]
final class SignalProblemsTest extends TestCase
{
    public function testSignalsTellsSignalAndResignalFromOtherStatements(): void
    {
        $parser = (new Instance())->connect()->semantics()->parser();

        self::assertSame([true, true, false], [(new SignalProblems())->signals($parser->parse("SIGNAL SQLSTATE '45000'")), (new SignalProblems())->signals($parser->parse('RESIGNAL')), (new SignalProblems())->signals($parser->parse('SELECT 1'))]);
    }

    public function testCheckRaisesAnUndefinedConditionBeforeAnUnknownSystemVariable(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1319);
        $this->expectExceptionMessage('Undefined CONDITION: c');

        $session->query('SIGNAL c SET MESSAGE_TEXT = @@nosuch');
    }

    public function testCheckRaisesABadSqlStateBeforeAnInvalidTemporalLiteral(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectExceptionCode(1407);

        $session->query("SIGNAL SQLSTATE '00000' SET MESSAGE_TEXT = DATE '2020-13-01'");
    }

    public function testCheckRaisesTheValueOfAnItemBeforeTheItemNamedAgain(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1193);

        $session->query("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = @@nosuch");
    }

    public function testCheckRaisesAnItemNamedTwiceBeforeTheValuesOfLaterItems(): void
    {
        $session = (new Instance('8.0.44'))->connect();

        $this->expectExceptionCode(1641);
        $this->expectExceptionMessage("Duplicate condition information item 'MESSAGE_TEXT'");

        $session->query("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a', MESSAGE_TEXT = 'b', MYSQL_ERRNO = @@nosuch");
    }

    public function testCheckRaisesTheConditionOfResignalBeforeTheMissingHandler(): void
    {
        $session = (new Instance())->connect();

        $this->expectExceptionCode(1407);

        $session->query("RESIGNAL SQLSTATE '00000'");
    }

    public function testCheckLeavesTheValuesToResolveToTheStatement(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'a'");

        (new SignalProblems())->check($operation, $session);

        $this->addToAssertionCount(1);
    }

    public function testValueRefusesAnInvalidTemporalLiteral(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $operation = $session->analyze("SIGNAL SQLSTATE '45000'");

        $this->expectExceptionCode(1525);
        $this->expectExceptionMessage("Incorrect DATETIME value: '2020-01-01 25:00:00'");

        (new SignalProblems())->value(new TemporalLiteral(TemporalForm::Timestamp, '2020-01-01 25:00:00'), $operation, $session);
    }
}
