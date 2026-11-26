<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight;

#[CoversClass(SequenceRight::class)]
final class SequenceRightTest extends TestCase
{
    public function testNativeSpelling(): void
    {
        self::assertSame(['SELECT', 'UPDATE', 'USAGE', 'RULE'], array_column(SequenceRight::cases(), 'value'));
    }
}
