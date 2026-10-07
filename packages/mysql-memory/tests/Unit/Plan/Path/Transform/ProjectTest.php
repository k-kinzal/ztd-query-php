<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Project::class)]
#[Small]
final class ProjectTest extends TestCase
{
    public function testWidthIsTheNumberOfExpressions(): void
    {
        self::assertSame(3, (new Project(new ZeroRows(1), [new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 0), new ColumnRead(Domain::integer(), 0)]))->width());
    }

    public function testWidthIsZeroWithoutExpressions(): void
    {
        self::assertSame(0, (new Project(new ZeroRows(5), []))->width());
    }
}
