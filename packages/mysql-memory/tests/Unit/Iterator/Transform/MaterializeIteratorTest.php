<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\InlineIterator;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\MaterializeIterator;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Plan\QueryPlan;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(MaterializeIterator::class)]
#[Small]
final class MaterializeIteratorTest extends TestCase
{
    public function testInitKeepsOnlyTheOutputColumnsOfTheQuery(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[1, 'sort key'], [2, 'other key']];
        $iterator = new MaterializeIterator(new Materialize(new QueryPlan($working, [Domain::integer()], ['a'])), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([1], $iterator->read());
        self::assertSame([2], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitComputesTheRowsWhenTheScanStarts(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1]];
        $iterator = new MaterializeIterator(new Materialize(new QueryPlan($working, [Domain::integer()], ['a'])), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));
        $working->rows = [[2]];

        self::assertSame([1], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitEvaluatesALateralQueryInsideTheJoinedRow(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $inline = new Inline([[new ColumnRead(Domain::integer(), 0, 1)]], 1);
        $iterator = new MaterializeIterator(new Materialize(new QueryPlan($inline, [Domain::integer()], ['a']), true), new InlineIterator($inline));
        $iterator->init(new Frame($context, [5], new Frame($context, [9])));

        self::assertSame([5], $iterator->read());
    }

    public function testInitEvaluatesAQueryThatIsNotLateralOutsideTheBlock(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $inline = new Inline([[new ColumnRead(Domain::integer(), 0, 1)]], 1);
        $iterator = new MaterializeIterator(new Materialize(new QueryPlan($inline, [Domain::integer()], ['a'])), new InlineIterator($inline));
        $iterator->init(new Frame($context, [5], new Frame($context, [9])));

        self::assertSame([9], $iterator->read());
    }

    public function testReadAnswersNullForAQueryWithoutRows(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $iterator = new MaterializeIterator(new Materialize(new QueryPlan($working, [Domain::integer()], ['a'])), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertNull($iterator->read());
    }
}
