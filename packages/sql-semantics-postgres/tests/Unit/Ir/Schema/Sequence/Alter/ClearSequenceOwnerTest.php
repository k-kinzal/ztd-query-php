<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ClearSequenceOwner;

#[CoversClass(ClearSequenceOwner::class)]
final class ClearSequenceOwnerTest extends TestCase
{
    public function testKeepsTheDeclaredEnumCase(): void
    {
        self::assertSame('remove', ClearSequenceOwner::Remove->value);
    }
}
