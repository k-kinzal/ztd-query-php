<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Numbers;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Call::class)]
#[Small]
final class CallTest extends TestCase
{
    public function testDomainAnswersTheDomainOfTheResult(): void
    {
        $domain = Domain::double();
        $call = new Call(new Routine('PI', 0, 0, static fn (): float => M_PI), [], $domain);

        self::assertSame($domain, $call->domain());
    }

    public function testEvaluateAppliesTheRoutineToTheArgumentsInOrder(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $text = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));
        $call = new Call(new Routine('CONCAT', 1, -1, (new Strings())->concat(...)), [new Constant($text, 'ab'), new Constant($text, 'cd')], Domain::string(8, Collation::known('utf8mb4_0900_ai_ci')));

        self::assertSame('abcd', $call->evaluate($frame));
    }

    public function testEvaluatePassesTheDomainOfTheResultToTheRoutine(): void
    {
        $instance = new Instance();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));
        $call = new Call(new Routine('ABS', 1, 1, (new Numbers())->abs(...)), [new Constant(Domain::decimal(3, 2), '-1.50')], Domain::decimal(3, 2));

        self::assertSame('1.50', $call->evaluate($frame));
    }

    public function testEvaluateComputesACallInAQuery(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT CONCAT('My', 'S', 'QL'), ABS(-5)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['MySQL', '5']], $result->rows);
        self::assertSame([Field::VarString, Field::LongLong], [$result->columns[0]->type, $result->columns[1]->type]);
    }
}
