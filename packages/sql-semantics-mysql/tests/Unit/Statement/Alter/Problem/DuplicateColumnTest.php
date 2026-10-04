<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\DuplicateColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DuplicateColumn::class)]
#[Small]
final class DuplicateColumnTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Column a would be defined more than once.', (new DuplicateColumn(new Name('a')))->message());
    }
}
