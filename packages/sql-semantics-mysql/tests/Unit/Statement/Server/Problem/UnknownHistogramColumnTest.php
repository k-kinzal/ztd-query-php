<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownHistogramColumn::class)]
#[Small]
final class UnknownHistogramColumnTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame("The column 'x' does not exist.", (new UnknownHistogramColumn(new Name('x')))->message());
    }
}
