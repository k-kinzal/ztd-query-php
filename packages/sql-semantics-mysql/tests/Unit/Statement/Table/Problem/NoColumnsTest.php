<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;

#[CoversClass(NoColumns::class)]
#[Small]
final class NoColumnsTest extends TestCase
{
    public function testMessageStatesTheProblem(): void
    {
        self::assertSame('A table must have at least 1 column.', (new NoColumns())->message());
    }
}
