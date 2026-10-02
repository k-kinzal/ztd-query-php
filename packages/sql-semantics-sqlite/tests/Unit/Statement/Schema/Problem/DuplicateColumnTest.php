<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DuplicateColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DuplicateColumn::class)]
#[Small]
final class DuplicateColumnTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame('Column A is defined more than once.', (new DuplicateColumn(new Name('A')))->message());
    }
}
