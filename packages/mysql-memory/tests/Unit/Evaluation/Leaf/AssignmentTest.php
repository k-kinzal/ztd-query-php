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
    public function testDomainAnswersTheDomainOfTheValue(): void
    {
        $value = new ColumnRead(Domain::integer(Field::Long, 11), 0);

        self::assertSame($value->domain(), (new Assignment('v', $value, Domain::integer()))->domain());
    }

    public function testEvaluateAssignsTheValueInTheStoredDomainAndAnswersIt(): void
    {
        $session = (new Instance())->connect();
        $stored = Domain::integer();
        $assignment = new Assignment('Total', new ColumnRead(Domain::integer(Field::Long, 11), 1), $stored);

        self::assertSame(42, $assignment->evaluate(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [7, 42])));
        self::assertSame([42, $stored], $session->variables->user('total'));
    }
}
