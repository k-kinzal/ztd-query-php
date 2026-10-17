<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

#[CoversClass(Accumulation::class)]
#[Small]
final class AccumulationTest extends TestCase
{
    public function testStartAnswersAFoldOfTheAggregate(): void
    {
        $accumulation = new Accumulation(AggregateFunction::Count, [], false, Domain::integer());

        self::assertSame($accumulation, $accumulation->start()->accumulation);
    }

    public function testStartAnswersAFreshFoldForEachGroup(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $accumulation = new Accumulation(AggregateFunction::Count, [new ColumnRead(Domain::integer(), 0)], false, Domain::integer());
        $first = $accumulation->start();
        $first->add(new Frame($context, [1]));
        $first->add(new Frame($context, [2]));
        $second = $accumulation->start();
        $second->add(new Frame($context, [3]));

        self::assertSame([2, 1], [$first->result(new Frame($context)), $second->result(new Frame($context))]);
    }
}
