<?php

declare(strict_types=1);

namespace Tests\Unit\Iterator\Combine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Iterator\Combine\ProjectionRead::class)]
#[Small]
final class ProjectionReadTest extends TestCase
{
    public function testReadDiscardsInputWithoutExecutingItsAssignment(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $table = new \MySqlMemory\Plan\Path\Source\WorkingTable(1);
        $table->rows = [[4], [8]];
        $domain = \MySqlMemory\Typing\Domain::integer();
        $assignment = new \MySqlMemory\Evaluation\Leaf\Assignment('x', new \MySqlMemory\Evaluation\Leaf\ColumnRead($domain, 0), $domain, $domain);
        $iterator = (new \MySqlMemory\Iterator\Builder())->build(new \MySqlMemory\Plan\Path\Transform\Project($table, [$assignment]));
        $iterator->init(new \MySqlMemory\Evaluation\Frame($context));
        $reads = new \MySqlMemory\Iterator\Combine\ProjectionRead();

        self::assertSame([4], $reads->read($iterator, false));
        self::assertNull($session->variables->user('x')[0]);
        self::assertSame([8], $reads->read($iterator, true));
        self::assertSame(8, $session->variables->user('x')[0]);
    }

    public function testCountedSeparatesConstantSelectsFromTableInput(): void
    {
        $reads = new \MySqlMemory\Iterator\Combine\ProjectionRead();
        $table = new \MySqlMemory\Plan\Path\Source\WorkingTable(1);

        self::assertFalse($reads->counted(new \MySqlMemory\Iterator\Source\SingleRowIterator()));
        self::assertTrue($reads->counted(new \MySqlMemory\Iterator\Source\WorkingTableIterator($table)));
    }

}
