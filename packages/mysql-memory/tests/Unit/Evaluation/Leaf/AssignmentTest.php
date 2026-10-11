<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Leaf;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Assignment::class)]
#[Small]
final class AssignmentTest extends TestCase
{
    public function testDomainAnswersTheResolvedResultSeparatelyFromTheAssignedValue(): void
    {
        $value = new ColumnRead(Domain::integer(Field::Long, 11), 0);

        $domain = Domain::integer();

        self::assertSame($domain, (new Assignment('v', $value, Domain::integer(), $domain))->domain());
    }

    public function testEvaluateAssignsTheValueInTheStoredDomainAndAnswersIt(): void
    {
        $session = (new Instance())->connect();
        $stored = Domain::integer();
        $assignment = new Assignment('Total', new ColumnRead(Domain::integer(Field::Long, 11), 1), $stored, Domain::integer());

        self::assertSame(42, $assignment->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [7, 42])));
        self::assertSame([42, $stored], $session->variables->user('total'));
    }

    public function testEvaluateConvertsBitColumnsIntoTheAssignedIntegerValue(): void
    {
        $session = (new Instance())->connect();
        $stored = Domain::integer(Field::LongLong, 21, true);
        $assignment = new Assignment('v', new ColumnRead(new Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Bit, Field::Bit, 8, 0, true), 0), $stored, $stored);

        self::assertSame(65, $assignment->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), ['A'])));
        self::assertSame([65, $stored], $session->variables->user('v'));
    }
}
