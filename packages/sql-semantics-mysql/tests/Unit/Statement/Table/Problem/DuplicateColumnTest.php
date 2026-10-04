<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DuplicateColumn::class)]
#[Small]
final class DuplicateColumnTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame('Duplicate column name a.', (new DuplicateColumn(new Name('a')))->message());
    }
}
