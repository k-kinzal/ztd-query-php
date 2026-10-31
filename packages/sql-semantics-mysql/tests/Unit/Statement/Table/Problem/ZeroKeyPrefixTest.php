<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ZeroKeyPrefix;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ZeroKeyPrefix::class)]
#[Small]
final class ZeroKeyPrefixTest extends TestCase
{
    public function testMessageKeepsTheDecodedColumnName(): void
    {
        self::assertSame("Key part 'a`b' length cannot be 0", (new ZeroKeyPrefix(new Name('a`b')))->message());
    }
}
