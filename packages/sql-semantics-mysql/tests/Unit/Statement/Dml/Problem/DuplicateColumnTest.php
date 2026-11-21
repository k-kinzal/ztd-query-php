<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn;

#[CoversClass(DuplicateColumn::class)]
#[Small]
final class DuplicateColumnTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame("Column 'a' specified twice", (new DuplicateColumn(new Name('a')))->message());
    }
}
