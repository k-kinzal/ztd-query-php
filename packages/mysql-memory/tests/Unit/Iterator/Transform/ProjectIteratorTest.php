<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Transform;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Iterator\Source\WorkingTableIterator;
use MySqlMemory\Iterator\Transform\ProjectIterator;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(ProjectIterator::class)]
#[Small]
final class ProjectIteratorTest extends TestCase
{
    public function testReadEvaluatesTheExpressionsOverEachInputRow(): void
    {
        $session = (new Instance())->connect();
        $text = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));
        $working = new WorkingTable(2);
        $working->rows = [[1, 'a'], [2, 'b']];
        $iterator = new ProjectIterator(new Project($working, [new ColumnRead($text, 1), new ColumnRead(Domain::integer(), 0), new Constant($text, 'x')]), new WorkingTableIterator($working));
        $iterator->init(new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)));

        self::assertSame(['a', 1, 'x'], $iterator->read());
        self::assertSame(['b', 2, 'x'], $iterator->read());
        self::assertNull($iterator->read());
    }

    public function testInitEvaluatesInTheFrameItIsGiven(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $working = new WorkingTable(1);
        $working->rows = [[1]];
        $iterator = new ProjectIterator(new Project($working, [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 0, 1)]), new WorkingTableIterator($working));
        $iterator->init(new Frame($context, [], new Frame($context, [9])));

        self::assertSame([1, 9], $iterator->read());
    }
}
