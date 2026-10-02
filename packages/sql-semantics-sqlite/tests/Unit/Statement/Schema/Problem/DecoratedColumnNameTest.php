<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DecoratedColumnName::class)]
#[Small]
final class DecoratedColumnNameTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame('Column name p of a column list is written with a collation or a sort order.', (new DecoratedColumnName(new Name('p')))->message());
    }
}
