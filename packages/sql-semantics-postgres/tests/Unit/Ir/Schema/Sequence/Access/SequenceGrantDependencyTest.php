<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency;

#[CoversClass(SequenceGrantDependency::class)]
final class SequenceGrantDependencyTest extends TestCase
{
    public function testDependencyModesUseDistinctNativeTokens(): void
    {
        self::assertSame(['RESTRICT', 'CASCADE'], array_column(SequenceGrantDependency::cases(), 'value'));
    }
}
