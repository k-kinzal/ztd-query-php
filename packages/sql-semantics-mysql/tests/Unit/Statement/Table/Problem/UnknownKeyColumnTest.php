<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UnknownKeyColumn::class)]
#[Small]
final class UnknownKeyColumnTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame("Key column b doesn't exist in table.", (new UnknownKeyColumn(new Name('b')))->message());
    }
}
