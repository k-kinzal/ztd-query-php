<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownColumn::class)]
#[Small]
final class UnknownColumnTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame('Column c does not exist in the table.', (new UnknownColumn(new Name('c')))->message());
    }
}
