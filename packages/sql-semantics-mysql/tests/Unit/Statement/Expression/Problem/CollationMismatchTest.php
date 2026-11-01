<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch;

#[CoversClass(CollationMismatch::class)]
#[Small]
final class CollationMismatchTest extends TestCase
{
    public function testMessageNamesTheCollationAndTheCharacterSet(): void
    {
        self::assertSame("COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb4'", (new CollationMismatch('latin1_bin', 'utf8mb4'))->message());
    }
}
