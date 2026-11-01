<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Transform;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Plan\Path\Source\ZeroRows;
use MySqlMemory\Plan\Path\Transform\ProjectDistinct;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectDistinct::class)]
#[Small]
final class ProjectDistinctTest extends TestCase
{
    public function testWidthIncludesDeferredFieldsOutsideTheComparisonKeys(): void
    {
        $domain = Domain::integer();
        $path = new ProjectDistinct(new ZeroRows(4), [new Constant($domain, 1), new Constant($domain, 2)], [1 => $domain]);

        self::assertSame(2, $path->width());
    }
}
