<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\ProjectDistinctIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\ProjectDistinct;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectDistinctIterator::class)]
#[Small]
final class ProjectDistinctIteratorTest extends TestCase
{
    public function testReadRetainsNullKeysAndIgnoresDeferredFieldsDuringComparison(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(2);
        $working->rows = [[1, null], [2, null], [3, 1]];
        $domain = Domain::integer()->withNullable(true);
        $path = new ProjectDistinct($working, [new ColumnRead($domain, 0), new ColumnRead($domain, 1)], [1 => $domain]);
        $iterator = new ProjectDistinctIterator($path, new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1, null], [3, 1], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testReadKeepsAllRowsWhenNoStoredFieldsParticipate(): void
    {
        $session = (new Instance())->connect();
        $working = new WorkingTable(1);
        $working->rows = [[1], [1]];
        $iterator = new ProjectDistinctIterator(new ProjectDistinct($working, [new ColumnRead(Domain::integer(), 0)], []), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame([[1], [1], null], [$iterator->read(), $iterator->read(), $iterator->read()]);
    }

    public function testInitForgetsKeysFromThePreviousExecution(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $working = new WorkingTable(1);
        $working->rows = [[1], [1]];
        $domain = Domain::integer();
        $iterator = new ProjectDistinctIterator(new ProjectDistinct($working, [new ColumnRead($domain, 0)], [$domain]), new WorkingTableIterator($working));
        $iterator->init($frame);
        $first = [$iterator->read(), $iterator->read()];
        $iterator->init($frame);

        self::assertSame([[1], null], $first);
        self::assertSame([1], $iterator->read());
    }
}
